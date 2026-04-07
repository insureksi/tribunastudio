jQuery(document).ready(function ($) {
    'use strict';

    var currency      = TSRB_Public.currency || 'IDR';
    var studiosData   = TSRB_Public.studios || {};
    var whatsappPhone = TSRB_Public.whatsapp_number || '';
    var initialUser   = TSRB_Public.current_user || null;

    // Lead time (disinkronkan dengan workflow[min_lead_time_hours]).
    var minLeadTimeHours = parseInt(TSRB_Public.min_lead_time_hours || 0, 10);

    // Booking & reschedule settings (disinkronkan dengan workflow).
    var bookingMinHoursBeforeStart = parseInt(TSRB_Public.booking_min_hours_before_start || TSRB_Public.min_lead_time_hours || 0, 10);
    var maxActiveBookingsPerUser   = parseInt(TSRB_Public.max_active_bookings_per_user || 0, 10);
    var paymentDeadlineHours       = parseInt(TSRB_Public.payment_deadline_hours || 0, 10);
    var rescheduleCutoffHours      = parseInt(TSRB_Public.reschedule_cutoff_hours || 0, 10);
    var rescheduleAllowPending     = !!TSRB_Public.reschedule_allow_pending;

    // Cancellation & refund policy (global) dari PHP.
    var cancellationPolicy = TSRB_Public.cancellation_policy || {};
    var allowMemberCancel  = !!cancellationPolicy.allow_member_cancel;
    var refundFullHours    = parseInt(cancellationPolicy.refund_full_hours_before || 0, 10);
    var refundPartialHours = parseInt(cancellationPolicy.refund_partial_hours_before || 0, 10);
    var refundPartialPct   = parseInt(cancellationPolicy.refund_partial_percent || 0, 10);
    var refundNoRefundHrs  = parseInt(cancellationPolicy.refund_no_refund_inside_hours || 0, 10);

    // Booking ID terakhir yang berhasil dibuat (dipakai untuk invoice & download).
    var currentBookingId = null;

    // ================== HELPER ==================

    function getSelectedStudioPrice() {
        var studioId = $('.tsrb-studio-radio:checked').val();
        if (studioId && studiosData[studioId]) {
            return parseFloat(studiosData[studioId].hourly_price || 0);
        }
        return 0;
    }

    function formatPrice(amount) {
        amount = parseFloat(amount || 0);
        return currency + ' ' + amount.toLocaleString();
    }

    function formatDateDisplay(isoDateStr) {
        if (!isoDateStr) return '';

        var parts = isoDateStr.split('-');
        if (parts.length !== 3) return isoDateStr;

        var yyyy = parseInt(parts[0], 10);
        var mm   = parseInt(parts[1], 10) - 1;
        var dd   = parseInt(parts[2], 10);

        var dateObj = new Date(yyyy, mm, dd);
        if (isNaN(dateObj.getTime())) {
            return isoDateStr;
        }

        var days    = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        var dayName = days[dateObj.getDay()];

        var ddStr = ('0' + dd).slice(-2);
        var mmStr = ('0' + (mm + 1)).slice(-2);
        var yyStr = String(yyyy).slice(2);

        return dayName + ', ' + ddStr + '-' + mmStr + '-' + yyStr;
    }

    function isLoggedInFrontend() {
        var wrapper = $('.tsrb-booking-wrapper');
        return wrapper.data('tsrb-logged-in') === 1 || wrapper.data('tsrb-logged-in') === '1';
    }

    // Frontend lead-time check.
    function isSlotRespectLeadTimeFrontend(dateStr, startTimeStr) {
        if (!minLeadTimeHours || minLeadTimeHours <= 0) {
            return true;
        }
        if (!dateStr || !startTimeStr) {
            return true;
        }

        var now = new Date();

        var partsDate = dateStr.split('-');
        if (partsDate.length !== 3) {
            return true;
        }
        var y = parseInt(partsDate[0], 10);
        var m = parseInt(partsDate[1], 10) - 1;
        var d = parseInt(partsDate[2], 10);

        var partsTime = startTimeStr.split(':');
        if (partsTime.length < 2) {
            return true;
        }
        var hh = parseInt(partsTime[0], 10);
        var ii = parseInt(partsTime[1], 10);
        var ss = partsTime.length > 2 ? parseInt(partsTime[2], 10) : 0;

        var bookingDate = new Date(y, m, d, hh, ii, ss);
        if (isNaN(bookingDate.getTime())) {
            return true;
        }

        var diffMs    = bookingDate.getTime() - now.getTime();
        var diffHours = diffMs / 3600000;

        return diffHours >= minLeadTimeHours;
    }

    // ================== PAYMENT TIMER FRONTEND ==================

    var tsrbFrontendTimerInterval     = null;
    var tsrbFrontendServerNowAtRender = 0;
    var tsrbFrontendClientNowAtRender = 0;

    function tsrbFrontendFormatCountdown(seconds) {
        seconds = parseInt(seconds, 10);
        if (isNaN(seconds) || seconds <= 0) {
            return 'Expired';
        }

        var h = Math.floor(seconds / 3600);
        seconds = seconds % 3600;
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;

        if (h > 0) {
            return h + 'h ' + (m > 0 ? m + 'm' : '');
        }
        if (m > 0) {
            return m + 'm';
        }
        return s + 's';
    }

    function tsrbFrontendGetServerNowApprox() {
        if (!tsrbFrontendServerNowAtRender) {
            return Math.floor(Date.now() / 1000);
        }
        var elapsedMs = Date.now() - tsrbFrontendClientNowAtRender;
        return tsrbFrontendServerNowAtRender + Math.floor(elapsedMs / 1000);
    }

    function tsrbFrontendUpdatePaymentTimers() {
        var serverNow = tsrbFrontendGetServerNowApprox();

        $('.js-tsrb-payment-timer-frontend').each(function () {
            var $badge  = $(this);
            var expires = parseInt($badge.data('expires'), 10);
            var $label  = $badge.find('.tsrb-timer-label');

            if (!expires || isNaN(expires)) {
                return;
            }

            var secondsLeft = expires - serverNow;

            if (secondsLeft <= 0) {
                if ($label.length) {
                    $label.text('Expired');
                } else {
                    $badge.text('Expired');
                }
                $badge
                    .removeClass('tsrb-payment-timer-badge--ok tsrb-payment-timer-badge--warn')
                    .addClass('tsrb-payment-timer-badge--expired');
                return;
            }

            if (secondsLeft <= 3600) {
                $badge
                    .removeClass('tsrb-payment-timer-badge--ok tsrb-payment-timer-badge--expired')
                    .addClass('tsrb-payment-timer-badge--warn');
            } else {
                $badge
                    .removeClass('tsrb-payment-timer-badge--warn tsrb-payment-timer-badge--expired')
                    .addClass('tsrb-payment-timer-badge--ok');
            }

            var labelText = tsrbFrontendFormatCountdown(secondsLeft);

            if ($label.length) {
                $label.text(labelText);
            } else {
                $badge.text(labelText);
            }
        });
    }

    function initFrontendPaymentTimers() {
        if (tsrbFrontendTimerInterval) {
            clearInterval(tsrbFrontendTimerInterval);
            tsrbFrontendTimerInterval = null;
        }

        var $table = $('.tsrb-user-bookings-table-history');
        if (!$table.length) {
            return;
        }

        var serverNowAttr = parseInt($table.data('tsrb-server-now'), 10);
        tsrbFrontendServerNowAtRender = isNaN(serverNowAttr) ? 0 : serverNowAttr;
        tsrbFrontendClientNowAtRender = Date.now();

        if (!$('.js-tsrb-payment-timer-frontend').length) {
            return;
        }

        tsrbFrontendUpdatePaymentTimers();
        tsrbFrontendTimerInterval = setInterval(tsrbFrontendUpdatePaymentTimers, 30000);
    }

    // ================== STEP NAVIGATION ==================

    function goToStep(step) {
        $('.tsrb-step').removeClass('tsrb-step-active');

        $('.tsrb-step').each(function () {
            var s = parseInt($(this).data('step'), 10);
            $(this).toggleClass('tsrb-step-completed', s < step);
        });

        $('.tsrb-step[data-step="' + step + '"]').addClass('tsrb-step-active');

        $('.tsrb-step-panel').removeClass('tsrb-step-panel-active');
        $('.tsrb-step-panel[data-step="' + step + '"]').addClass('tsrb-step-panel-active');

        var $wrapper = $('.tsrb-booking-wrapper');
        if ($wrapper.length) {
            $('html, body').animate(
                { scrollTop: $wrapper.offset().top - 20 },
                300
            );
        }

        // FIX Bug 1 & Bug 2c: Setiap kali Step 1 ditampilkan, paksa FullCalendar
        // hitung ulang dimensinya. Ini mengatasi layout rusak akibat kalender
        // di-render saat container tersembunyi (setelah login/register atau reset booking).
        if (step === 1 && bookingCalendar) {
            setTimeout(function () {
                bookingCalendar.updateSize();
            }, 50);
        }

        setTimeout(function () {
            if (step === 2) {
                var $name = $('#tsrb-full-name');
                if ($name.length) {
                    $name.trigger('focus');
                }
            } else if (step === 3) {
                var $coupon = $('#tsrb-coupon-code');
                if ($coupon.length) {
                    $coupon.trigger('focus');
                }
            }
        }, 350);
    }

    $(document).on('click', '.tsrb-next-step', function () {
        var next = $(this).data('next-step');

        if (next === 2 && !isLoggedInFrontend()) {
            openAuthModal('login');
            return;
        }

        if (next === 2) {
            var dateVal  = $('#tsrb-selected-date-text').data('value');
            var slotFrom = $('#tsrb-slot-start').val();
            var slotTo   = $('#tsrb-slot-end').val();

            if (!dateVal || !slotFrom || !slotTo) {
                alert('Silakan pilih tanggal dan minimal satu jam sesi.');
                return;
            }

            if (minLeadTimeHours > 0 && !isSlotRespectLeadTimeFrontend(dateVal, slotFrom)) {
                alert(
                    'Jam mulai terlalu dekat dengan waktu sekarang. ' +
                    'Jarak minimal booking adalah ' + minLeadTimeHours + ' jam sebelum jam mulai.'
                );
                return;
            }

            if (getSelectedStudioPrice() <= 0) {
                alert('Harga studio belum dikonfigurasi. Silakan hubungi admin.');
                return;
            }
        } else if (next === 3) {
            if (!$('#tsrb-full-name').val() || !$('#tsrb-email').val() || !$('#tsrb-phone').val()) {
                alert('Silakan lengkapi data kontak Anda (nama, email, dan No. HP/WhatsApp).');
                return;
            }
            buildSummary();
        }
        goToStep(next);
    });

    $(document).on('click', '.tsrb-prev-step', function () {
        var prev = $(this).data('prev-step');
        goToStep(prev);
    });

    // ================== STUDIO CARD SELECTION ==================

    $(document).on('change', '.tsrb-studio-radio', function () {
        $('.tsrb-studio-card').removeClass('tsrb-studio-card-selected');
        $(this).closest('.tsrb-studio-card').addClass('tsrb-studio-card-selected');

        updatePricing();

        var dateStr = $('#tsrb-selected-date-text').data('value');
        if (dateStr) {
            loadSlotsForDate(dateStr);
        }
    });

    // ================== BOOKING CALENDAR + SLOTS ==================

    var $bookingCalendarEl = document.getElementById('tsrb-booking-calendar');
    var selectedDate       = null;
    var slotsCache         = {};
    var couponData         = null;

    var pricing = {
        durationHours: 0,
        basePrice:     0,
        addonsPrice:   0,
        total:         0,
        final:         0,
        discount:      0
    };

    // FIX Bug 1 & Bug 2b & Bug 2c: Pindahkan instance kalender ke outer scope
    // agar bisa diakses oleh goToStep() dan tsrbResetFullBookingState().
    var bookingCalendar = null;

    function tsrbResetFullBookingState() {
        var $form = $('#tsrb-booking-form');
        if ($form.length && $form[0].reset) {
            $form[0].reset();
        }

        // FIX Bug 2b: Reset state internal selectedDate dan slotsCache
        // agar sinkron dengan DOM setelah reset.
        selectedDate = null;
        slotsCache   = {};

        // FIX Bug 2b: Bersihkan visual selection di FullCalendar.
        // Hapus class tsrb-date-selected dari semua cell kalender,
        // lalu panggil unselect() jika API tersedia.
        $('.tsrb-date-selected').removeClass('tsrb-date-selected');
        if (bookingCalendar) {
            if (typeof bookingCalendar.unselect === 'function') {
                bookingCalendar.unselect();
            }
        }

        $('#tsrb-selected-date-text').text('-').data('value', '');
        $('#tsrb-time-slots').html('<p class="tsrb-info">Silakan pilih tanggal untuk melihat jam yang tersedia.</p>');
        $('#tsrb-slot-start').val('');
        $('#tsrb-slot-end').val('');
        $('.tsrb-slot-available').removeClass('tsrb-slot-selected');

        $('.tsrb-studio-card').removeClass('tsrb-studio-card-selected');
        var $firstRadio = $('.tsrb-studio-radio').first();
        if ($firstRadio.length) {
            $firstRadio.prop('checked', true).trigger('change');
            $firstRadio.closest('.tsrb-studio-card').addClass('tsrb-studio-card-selected');
        }

        $('.tsrb-addon-item input[type="checkbox"]').prop('checked', false);

        pricing.durationHours = 0;
        pricing.basePrice     = 0;
        pricing.addonsPrice   = 0;
        pricing.total         = 0;
        pricing.discount      = 0;
        pricing.final         = 0;

        $('#tsrb-duration-hours').text('0');
        $('#tsrb-base-price').text(formatPrice(0));
        $('#tsrb-addons-price').text(formatPrice(0));
        $('#tsrb-total-price').text(formatPrice(0));
        $('#tsrb-final-price-text').text(formatPrice(0));
        $('#tsrb-summary-invoice').html('');
        $('#tsrb-booking-result').html('');

        currentBookingId = null;
        $('#tsrb-download-invoice-wrapper').remove();

        couponData = null;
        $('#tsrb-coupon-code').val('');
        $('#tsrb-coupon-message').text('').css('color', '');

        // goToStep(1) sudah memanggil bookingCalendar.updateSize() via fix Bug 2c.
        goToStep(1);
    }

    function initBookingCalendar() {
        if (!$bookingCalendarEl || typeof FullCalendar === 'undefined') {
            return;
        }

        // FIX Bug 1 & Bug 2c: Simpan instance ke variabel outer scope bookingCalendar,
        // bukan ke variabel lokal 'calendar' yang tidak bisa diakses dari luar fungsi ini.
        bookingCalendar = new FullCalendar.Calendar($bookingCalendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            validRange: function (nowDate) {
                return { start: nowDate };
            },
            dateClick: function (info) {
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                var clicked = new Date(info.date);
                clicked.setHours(0, 0, 0, 0);

                if (clicked < today) {
                    return;
                }

                var dateStr = info.dateStr;
                selectedDate = dateStr;

                $('.tsrb-date-selected').removeClass('tsrb-date-selected');
                $(info.dayEl).addClass('tsrb-date-selected');

                $('#tsrb-selected-date-text')
                    .text(formatDateDisplay(dateStr))
                    .data('value', dateStr);

                $('.tsrb-selected-date-help').removeClass('tsrb-selected-date-help-empty').hide();

                loadSlotsForDate(dateStr);
            },
            dayCellDidMount: function (info) {
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                var cellDate = new Date(info.date);
                cellDate.setHours(0, 0, 0, 0);

                if (cellDate < today) {
                    info.el.classList.add('tsrb-date-past');
                } else {
                    info.el.classList.add('tsrb-date-future');
                    info.el.setAttribute('title', 'Klik untuk memilih tanggal ini');
                }
            }
        });

        bookingCalendar.render();
    }

    function loadSlotsForDate(dateStr) {
        $('#tsrb-time-slots').html('<p class="tsrb-info">Memuat jam tersedia...</p>');

        var studioId = $('.tsrb-studio-radio:checked').val() || 0;

        $.ajax({
            url: TSRB_Public.ajax_url,
            dataType: 'json',
            data: {
                action:    'tsrb_get_availability',
                nonce:     TSRB_Public.nonce,
                date:      dateStr,
                studio_id: studioId
            }
        }).done(function (response) {
            if (!response.success) {
                var msg = (response.data && response.data.message) ? response.data.message : 'Terjadi kesalahan saat memuat jam.';
                $('#tsrb-time-slots').html('<p class="tsrb-error">' + msg + '</p>');
                return;
            }

            var data = response.data;
            slotsCache[dateStr] = data;
            renderSlots(data);
        }).fail(function () {
            $('#tsrb-time-slots').html('<p class="tsrb-error">Gagal memuat jam tersedia.</p>');
        });
    }

    function renderSlots(data) {
        var slots = data.slots || [];
        var html  = '';

        if (slots.length === 0) {
            html = '<p class="tsrb-info">Tidak ada jam tersedia di tanggal ini.</p>';
            $('#tsrb-time-slots').html(html);
            $('#tsrb-slot-start').val('');
            $('#tsrb-slot-end').val('');
            pricing.durationHours = 0;
            updatePricing();
            return;
        }

        html += '<div class="tsrb-slots-list">';
        slots.forEach(function (slot, index) {
            var label       = slot.start_time.substring(0, 5) + ' - ' + slot.end_time.substring(0, 5);
            var statusLabel = (slot.status === 'booked') ? 'BOOKED' : 'AVAILABLE';
            var cssClass    = 'tsrb-slot-item';
            if (slot.status === 'booked') {
                cssClass += ' tsrb-slot-booked';
            } else {
                cssClass += ' tsrb-slot-available';
            }
            html += '<div class="' + cssClass + '" ' +
                'data-index="' + index + '" ' +
                'data-start="' + slot.start_time + '" ' +
                'data-end="' + slot.end_time + '">' +
                '<div class="tsrb-slot-time">' + label + '</div>' +
                '<div class="tsrb-slot-status">' + statusLabel + '</div>' +
                '</div>';
        });
        html += '</div>';

        $('#tsrb-time-slots').html(html);

        $('#tsrb-slot-start').val('');
        $('#tsrb-slot-end').val('');
        pricing.durationHours = 0;
        updatePricing();
    }

    $(document).on('click', '.tsrb-slot-available', function () {
        var $this = $(this);

        if ($this.hasClass('tsrb-slot-selected')) {
            $('.tsrb-slot-available').removeClass('tsrb-slot-selected');
            $('#tsrb-slot-start').val('');
            $('#tsrb-slot-end').val('');
            pricing.durationHours = 0;
            updatePricing();
            return;
        }

        var $selected = $('.tsrb-slot-selected');
        if ($selected.length === 0) {
            $this.addClass('tsrb-slot-selected');
            var start = $this.data('start');
            var end   = $this.data('end');
            $('#tsrb-slot-start').val(start);
            $('#tsrb-slot-end').val(end);
            pricing.durationHours = 1;
            updatePricing();
            return;
        }

        var firstIndex = parseInt($selected.first().data('index'), 10);
        var lastIndex  = parseInt($selected.last().data('index'), 10);
        var currentIdx = parseInt($this.data('index'), 10);

        if (currentIdx === lastIndex + 1) {
            $this.addClass('tsrb-slot-selected');
        } else if (currentIdx === firstIndex - 1) {
            $this.addClass('tsrb-slot-selected');
        } else {
            $('.tsrb-slot-available').removeClass('tsrb-slot-selected');
            $this.addClass('tsrb-slot-selected');
        }

        var selected = $('.tsrb-slot-selected').sort(function (a, b) {
            return parseInt($(a).data('index'), 10) - parseInt($(b).data('index'), 10);
        });

        var startTime = selected.first().data('start');
        var endTime   = selected.last().data('end');

        $('#tsrb-slot-start').val(startTime);
        $('#tsrb-slot-end').val(endTime);

        pricing.durationHours = selected.length > 0 ? selected.length : 0;
        updatePricing();
    });

    // ================== PRICING & COUPON ==================

    function updatePricing() {
        var duration   = pricing.durationHours || 0;
        var baseHourly = getSelectedStudioPrice();

        pricing.basePrice = duration * baseHourly;

        var addonsTotal = 0;
        $('.tsrb-addon-item input[type="checkbox"]:checked').each(function () {
            var price = parseFloat($(this).data('price') || 0);
            addonsTotal += price;
        });
        pricing.addonsPrice = addonsTotal;

        pricing.total = pricing.basePrice + pricing.addonsPrice;

        if (couponData) {
            if (couponData.type === 'percent') {
                pricing.discount = pricing.total * (couponData.value / 100);
            } else {
                pricing.discount = couponData.value;
            }
            if (pricing.discount > pricing.total) {
                pricing.discount = pricing.total;
            }
        } else {
            pricing.discount = 0;
        }

        pricing.final = pricing.total - pricing.discount;

        $('#tsrb-duration-hours').text(duration);
        $('#tsrb-base-price').text(formatPrice(pricing.basePrice));
        $('#tsrb-addons-price').text(formatPrice(pricing.addonsPrice));
        $('#tsrb-total-price').text(formatPrice(pricing.total));
        $('#tsrb-final-price-text').text(formatPrice(pricing.final));

        var activeStep = $('.tsrb-step.tsrb-step-active').data('step');
        if (activeStep === 3) {
            buildSummary();
        }
    }

    $(document).on('change', '.tsrb-addon-item input[type="checkbox"]', function () {
        updatePricing();
    });

    $('#tsrb-apply-coupon').on('click', function () {
        var code = $('#tsrb-coupon-code').val().trim();
        $('#tsrb-coupon-message').text('').css('color', '');

        if (!code) {
            $('#tsrb-coupon-message').text('Silakan masukkan kode kupon.');
            return;
        }

        $.ajax({
            url: TSRB_Public.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'tsrb_validate_coupon',
                nonce:  TSRB_Public.nonce,
                code:   code
            }
        }).done(function (response) {
            if (!response.success) {
                $('#tsrb-coupon-message')
                    .text(response.data && response.data.message ? response.data.message : 'Kupon tidak valid.')
                    .css('color', 'red');
                couponData = null;
                updatePricing();
                return;
            }
            couponData = response.data;
            $('#tsrb-coupon-message').text('Kupon berhasil digunakan.').css('color', 'green');
            updatePricing();
        }).fail(function () {
            $('#tsrb-coupon-message').text('Gagal memeriksa kupon.').css('color', 'red');
        });
    });

    // ================== SUMMARY & WHATSAPP ==================

    function buildSummary() {
        var dateRaw = $('#tsrb-selected-date-text').data('value') || '';
        var date    = formatDateDisplay(dateRaw);
        var start   = $('#tsrb-slot-start').val();
        var end     = $('#tsrb-slot-end').val();

        var studioName = $('.tsrb-studio-radio:checked')
            .closest('.tsrb-studio-card')
            .find('.tsrb-studio-name')
            .text() || '';

        var duration = pricing.durationHours || 0;
        var base     = pricing.basePrice || 0;

        // FIX: Gunakan pricing.addonsPrice (state terpusat dari updatePricing())
        // sebagai sumber kebenaran, bukan hitung ulang dari DOM.
        var addons      = [];
        var addonsTotal = pricing.addonsPrice || 0;

        $('.tsrb-addon-item input[type="checkbox"]:checked').each(function () {
            var $wrap = $(this).closest('.tsrb-addon-item');
            var name  = $wrap.find('.tsrb-addon-name').contents().filter(function () {
                return this.nodeType === 3;
            }).first().text().trim();
            var price = parseFloat($(this).data('price') || 0);
            addons.push({ name: name, price: price });
        });

        var couponCode = $('#tsrb-coupon-code').val().trim();
        var discount   = pricing.discount || 0;

        // FIX: Jangan gunakan `pricing.final || pricing.total` karena jika pricing.final === 0
        // (diskon 100%) JS akan salah fallback ke pricing.total.
        var total = (typeof pricing.final === 'number') ? pricing.final : (pricing.total || 0);

        var html = '';

        html += '<div class="tsrb-invoice-header">';
        html += '<div class="tsrb-invoice-title">Detail Booking Anda</div>';
        html += '<div class="tsrb-invoice-meta">';
        html += '<div><strong>ID Booking:</strong> <span class="tsrb-invoice-booking-id">' +
            (currentBookingId ? currentBookingId : '-') + '</span></div>';
        html += '<div><strong>Tanggal:</strong> ' + (date || '-') + '</div>';
        html += '</div>';
        html += '</div>';

        html += '<table class="tsrb-invoice-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="tsrb-invoice-col-item">Item</th>';
        html += '<th class="tsrb-invoice-col-detail">Detail</th>';
        html += '<th class="tsrb-invoice-col-subtotal">Sub-Total</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';

        html += '<tr>';
        html += '<td class="tsrb-invoice-cell-item">Sewa Studio: ' + (studioName || '-') + '</td>';
        html += '<td class="tsrb-invoice-cell-detail">';
        html += 'Tanggal: ' + (date || '-') + '<br>';
        html += 'Jam: ' + (start || '-') + ' - ' + (end || '-') + '<br>';
        html += 'Durasi: ' + duration + ' jam';
        html += '</td>';
        html += '<td class="tsrb-invoice-cell-subtotal">' + formatPrice(base) + '</td>';
        html += '</tr>';

        if (addons.length > 0) {
            html += '<tr>';
            html += '<td class="tsrb-invoice-cell-item">Add-ons</td>';
            html += '<td class="tsrb-invoice-cell-detail">';
            addons.forEach(function (addon, idx) {
                html += addon.name;
                if (addon.price > 0) {
                    html += ' <span class="tsrb-invoice-addon-price">(' + formatPrice(addon.price) + ')</span>';
                }
                if (idx < addons.length - 1) {
                    html += '<br>';
                }
            });
            html += '</td>';
            html += '<td class="tsrb-invoice-cell-subtotal">' + formatPrice(addonsTotal) + '</td>';
            html += '</tr>';
        } else {
            html += '<tr>';
            html += '<td class="tsrb-invoice-cell-item">Add-ons</td>';
            html += '<td class="tsrb-invoice-cell-detail">-</td>';
            html += '<td class="tsrb-invoice-cell-subtotal">' + formatPrice(0) + '</td>';
            html += '</tr>';
        }

        html += '<tr>';
        html += '<td class="tsrb-invoice-cell-item">Kupon</td>';
        html += '<td class="tsrb-invoice-cell-detail">';
        if (couponCode) {
            html += 'Kode Kupon: ' + couponCode;
            if (discount > 0) {
                html += '<br>Potongan: ' + formatPrice(discount);
            }
        } else {
            html += 'Tidak menggunakan kupon';
        }
        html += '</td>';
        html += '<td class="tsrb-invoice-cell-subtotal">';
        html += discount > 0 ? ('- ' + formatPrice(discount)) : formatPrice(0);
        html += '</td>';
        html += '</tr>';

        html += '<tr class="tsrb-invoice-row-total">';
        html += '<td class="tsrb-invoice-cell-item"><strong>Total</strong></td>';
        html += '<td class="tsrb-invoice-cell-detail"></td>';
        html += '<td class="tsrb-invoice-cell-subtotal tsrb-invoice-total-amount"><strong>' + formatPrice(total) + '</strong></td>';
        html += '</tr>';

        html += '</tbody>';
        html += '</table>';

        html += '<div id="tsrb-download-invoice-wrapper" class="tsrb-download-invoice-wrapper"></div>';

        $('#tsrb-summary-invoice').html(html);
        $('#tsrb-final-price-text').text(formatPrice(total));

        if (currentBookingId && TSRB_Public.invoice_url) {
            ensureDownloadInvoiceButton();
        }
    }

    function ensureDownloadInvoiceButton() {
        var $wrapper = $('#tsrb-download-invoice-wrapper');
        if (!$wrapper.length) {
            return;
        }
        $wrapper.empty();

        if (!currentBookingId || !TSRB_Public.invoice_url) {
            return;
        }

        var $btn = $('<button>', {
            type: 'button',
            class: 'tsrb-btn tsrb-btn-secondary button tsrb-download-invoice-btn',
            text: 'Download Invoice'
        });

        $wrapper.append($btn);
    }

    $(document).on('click', '.tsrb-download-invoice-btn', function () {
        if (!currentBookingId || !TSRB_Public.invoice_url) {
            alert('Invoice belum tersedia.');
            return;
        }

        var url = TSRB_Public.invoice_url +
            (TSRB_Public.invoice_url.indexOf('?') === -1 ? '?' : '&') +
            'tsrb_invoice=1&booking_id=' + encodeURIComponent(currentBookingId) +
            '&nonce=' + encodeURIComponent(TSRB_Public.nonce);

        window.open(url, '_blank');
    });

    $('#tsrb-whatsapp-button').on('click', function () {
        if (!whatsappPhone) {
            alert('Nomor WhatsApp admin belum dikonfigurasi.');
            return;
        }

        var phoneNormalized = whatsappPhone.replace(/[^0-9]/g, '');

        var name  = $('#tsrb-full-name').val();
        var phone = $('#tsrb-phone').val();
        var date  = $('#tsrb-selected-date-text').data('value') || '';
        var start = $('#tsrb-slot-start').val();
        var end   = $('#tsrb-slot-end').val();

        var text = 'Halo, saya sudah melakukan booking.\n' +
            'Nama: ' + name + '\n' +
            'No. HP/WA: ' + phone + '\n' +
            '------------ \n' +
            'Dengan detail booking: \n' +
            'Tanggal: ' + formatDateDisplay(date) + '\n' +
            'Jam: ' + start + ' - ' + end + '\n' +
            'Total Akhir: ' + formatPrice(pricing.final);

        var url = 'https://wa.me/' + encodeURIComponent(phoneNormalized) +
            '?text=' + encodeURIComponent(text);

        window.open(url, '_blank');
    });

    // ================== BOOKING POLICY MODAL (STEP 3) ==================

    function buildBookingPolicyHtml() {
        var html = '';

        html += '<div class="tsrb-modal-rules-block tsrb-modal-rules-booking">';
        html += '<strong>Aturan Booking</strong>';
        html += '<ul>';

        var leadHours = bookingMinHoursBeforeStart || minLeadTimeHours;
        if (leadHours > 0) {
            html += '<li>Booking harus dibuat minimal ' + leadHours + ' jam sebelum jam mulai.</li>';
        }
        if (maxActiveBookingsPerUser > 0) {
            html += '<li>Setiap member dapat memiliki maksimal ' + maxActiveBookingsPerUser + ' booking aktif sekaligus.</li>';
        }
        if (paymentDeadlineHours > 0) {
            html += '<li>Jika pembayaran tidak diterima dalam waktu ' + paymentDeadlineHours +
                ' jam sejak booking dibuat, booking akan dibatalkan otomatis.</li>';
        }

        html += '</ul>';
        html += '</div>';

        html += '<div class="tsrb-modal-rules-block tsrb-modal-rules-reschedule">';
        html += '<strong>Aturan Reschedule</strong>';
        html += '<ul>';
        html += '<li>Perubahan jadwal hanya dapat diajukan dengan menghubungi admin (WhatsApp atau e-mail) dan akan diproses jika slot jadwal baru masih tersedia.</li>';

        if (rescheduleCutoffHours > 0) {
            html += '<li>Permohonan reschedule dapat diajukan maksimal ' +
                rescheduleCutoffHours + ' jam sebelum jam mulai booking awal.</li>';
        }
        if (rescheduleAllowPending) {
            html += '<li>Reschedule hanya berlaku untuk booking dengan status Menunggu Pembayaran dan Sudah Dibayar.</li>';
        } else {
            html += '<li>Reschedule hanya berlaku untuk booking dengan status Sudah Dibayar.</li>';
        }

        html += '</ul>';
        html += '</div>';

        html += '<div class="tsrb-modal-rules-block tsrb-modal-rules-refund">';
        html += '<strong>Aturan Pembatalan & Refund</strong>';
        html += '<ul>';

        if (allowMemberCancel) {
            html += '<li>Anda dapat mengajukan pembatalan dari menu "Booking Saya" pada dashboard member.</li>';
        } else {
            html += '<li>Pengajuan pembatalan hanya dapat dilakukan dengan menghubungi admin.</li>';
        }
        if (refundFullHours > 0) {
            html += '<li>Refund penuh diberikan jika pembatalan dilakukan minimal ' + refundFullHours + ' jam sebelum jam mulai.</li>';
        }
        if (refundPartialHours > 0 && refundPartialPct > 0) {
            html += '<li>Refund parsial sebesar ' + refundPartialPct +
                '% diberikan jika pembatalan dilakukan sebelum ' +
                refundPartialHours + ' jam dari jam mulai di hari H.</li>';
        }
        if (refundNoRefundHrs > 0) {
            html += '<li>Tidak ada refund jika pembatalan dilakukan kurang dari ' + refundNoRefundHrs + ' jam sebelum jam mulai.</li>';
        }

        html += '</ul>';
        html += '</div>';

        return html;
    }

    $(document).on('click', '.tsrb-btn-booking-policy', function (e) {
        e.preventDefault();

        var $modal = $('#tsrb-booking-policy-modal');
        if (!$modal.length) {
            return;
        }

        var $body = $modal.find('.tsrb-modal-booking-policy-body, .tsrb-booking-policy-body');
        if ($body.length) {
            $body.html(buildBookingPolicyHtml());
        }

        var $errorBox = $modal.find('.tsrb-booking-policy-error');
        if ($errorBox.length) {
            $errorBox.hide().text('');
        }

        $modal.show();
    });

    $(document).on(
        'click',
        '#tsrb-booking-policy-modal .tsrb-modal-close,' +
        ' #tsrb-booking-policy-modal .tsrb-modal-cancel,' +
        ' #tsrb-booking-policy-modal .tsrb-modal-backdrop',
        function (e) {
            e.preventDefault();
            $('#tsrb-booking-policy-modal').hide();
        }
    );

    // ================== SUBMIT BOOKING ==================

    $('#tsrb-booking-form').on('submit', function (e) {
        e.preventDefault();

        var dateVal  = $('#tsrb-selected-date-text').data('value') || '';
        var slotFrom = $('#tsrb-slot-start').val();

        if (minLeadTimeHours > 0 && dateVal && slotFrom && !isSlotRespectLeadTimeFrontend(dateVal, slotFrom)) {
            alert(
                'Jam mulai terlalu dekat dengan waktu sekarang. ' +
                'Jarak minimal booking adalah ' + minLeadTimeHours + ' jam sebelum jam mulai.'
            );
            return;
        }

        var form     = document.getElementById('tsrb-booking-form');
        var formData = new FormData(form);

        formData.append('date', dateVal);
        formData.append('duration', pricing.durationHours);
        formData.append('nonce', TSRB_Public.nonce);
        formData.append('action', 'tsrb_submit_booking');

        $('#tsrb-submit-booking').prop('disabled', true);
        $('#tsrb-booking-result').html('<p class="tsrb-info">Mengirim data booking...</p>');

        $.ajax({
            url: TSRB_Public.ajax_url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            $('#tsrb-submit-booking').prop('disabled', false);

            if (!response.success) {
                $('#tsrb-booking-result').html(
                    '<p class="tsrb-error">' +
                        (response.data && response.data.message ? response.data.message : 'Booking gagal dikirim.') +
                    '</p>'
                );
                var $wrapper = $('.tsrb-booking-wrapper');
                if ($wrapper.length) {
                    $('html, body').animate({ scrollTop: $wrapper.offset().top - 20 }, 300);
                }
                return;
            }

            var data = response.data;

            currentBookingId = data.booking_id || null;

            if (currentBookingId) {
                $('.tsrb-invoice-booking-id').text(currentBookingId);
            }

            if (currentBookingId && TSRB_Public.invoice_url) {
                ensureDownloadInvoiceButton();
            }

            var msg  = '<p class="tsrb-success">' + data.message + '</p>';
            msg     += '<p><strong>ID Booking:</strong> ' + data.booking_id + '</p>';
            if (data.google_cal) {
                msg += '<p><a href="' + data.google_cal + '" target="_blank" rel="noopener noreferrer">Tambahkan ke Google Calendar</a></p>';
            }
            $('#tsrb-booking-result').html(msg);

            var modalHtml  = '<div class="tsrb-booking-success-title">Booking Berhasil</div>';
            modalHtml     += '<div class="tsrb-booking-success-message">' +
                (data.message || 'Booking submitted. Please wait for admin confirmation.') + '</div>';
            modalHtml     += '<div class="tsrb-booking-success-id">ID Booking: ' + data.booking_id + '</div>';
            if (data.google_cal) {
                modalHtml += '<p><a href="' + data.google_cal + '" target="_blank" rel="noopener noreferrer">Tambahkan ke Google Calendar</a></p>';
            }
            modalHtml     += '<div class="tsrb-booking-success-actions">';
            modalHtml     +=   '<button type="button" class="tsrb-btn tsrb-btn-primary tsrb-booking-success-ok">OK</button>';
            modalHtml     += '</div>';

            $('#tsrb-booking-success-body').html(modalHtml);
            $('#tsrb-booking-success-modal').show();

            $(document)
                .off('click.tsrbBookingSuccess')
                .on('click.tsrbBookingSuccess',
                    '.tsrb-booking-success-ok,' +
                    ' #tsrb-booking-success-modal .tsrb-account-modal-close,' +
                    ' #tsrb-booking-success-modal .tsrb-account-modal-overlay',
                    function () {
                        $('#tsrb-booking-success-modal').hide();
                    }
                );

            var $backBtn = $('.tsrb-step3-back');
            if ($backBtn.length) {
                $backBtn.text('Booking tanggal lain');
                $backBtn.data('prev-step', 1);

                // FIX Bug 2a: Saat user klik "Booking tanggal lain", refresh nonce
                // terlebih dahulu sebelum reset state. Ini memastikan AJAX call
                // berikutnya (tsrb_get_availability) menggunakan nonce yang segar
                // dan tidak gagal karena nonce yang sudah dipakai submit booking.
                $backBtn.off('click.tsrbBackAfterSuccess').on('click.tsrbBackAfterSuccess', function (ev) {
                    ev.preventDefault();

                    $.ajax({
                        url: TSRB_Public.ajax_url,
                        method: 'POST',
                        dataType: 'json',
                        data: { action: 'tsrb_refresh_public_nonce' }
                    }).always(function (res) {
                        // Perbarui nonce jika refresh berhasil; lanjutkan reset
                        // bahkan jika refresh gagal agar UX tidak terhenti.
                        if (res && res.success && res.data && res.data.nonce) {
                            TSRB_Public.nonce = res.data.nonce;
                        }
                        tsrbResetFullBookingState();
                    });
                });
            }
        }).fail(function (xhr) {
            $('#tsrb-submit-booking').prop('disabled', false);

            var msg = 'Permintaan booking gagal dikirim.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = xhr.responseJSON.data.message;
            }

            $('#tsrb-booking-result').html('<p class="tsrb-error">' + msg + '</p>');

            var $wrapper = $('.tsrb-booking-wrapper');
            if ($wrapper.length) {
                $('html, body').animate({ scrollTop: $wrapper.offset().top - 20 }, 300);
            }
        });
    });

    // ================== PUBLIC CALENDAR ==================

    var $publicCalendarEl = document.getElementById('tsrb-public-calendar');

    function initPublicCalendar() {
        if (!$publicCalendarEl || typeof FullCalendar === 'undefined') {
            return;
        }

        var calendar = new FullCalendar.Calendar($publicCalendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            validRange: function (nowDate) {
                return { start: nowDate };
            },
            events: function (info, success, failure) {
                var start    = info.startStr;
                var end      = info.endStr;
                var studioId = $('#tsrb-public-studio-id').val() || 0;

                var startDate = new Date(start + 'T00:00:00');
                var endDate   = new Date(end + 'T00:00:00');

                var promises = [];
                var events   = [];

                for (var d = new Date(startDate); d < endDate; d.setDate(d.getDate() + 1)) {
                    (function (dateObj) {
                        var dStr = dateObj.toISOString().slice(0, 10);
                        promises.push(
                            $.ajax({
                                url: TSRB_Public.ajax_url,
                                dataType: 'json',
                                data: {
                                    action:    'tsrb_get_availability',
                                    nonce:     TSRB_Public.nonce,
                                    date:      dStr,
                                    studio_id: studioId
                                }
                            }).done(function (response) {
                                if (response.success) {
                                    var status     = response.data.status;
                                    var colorClass = '';
                                    if (status === 'available')      { colorClass = 'fc-available'; }
                                    else if (status === 'partial')   { colorClass = 'fc-partial'; }
                                    else if (status === 'full')      { colorClass = 'fc-full'; }
                                    else if (status === 'closed')    { colorClass = 'fc-closed'; }

                                    if (colorClass) {
                                        events.push({
                                            start:     dStr,
                                            end:       dStr,
                                            display:   'background',
                                            className: colorClass
                                        });
                                    }
                                }
                            })
                        );
                    })(new Date(d));
                }

                $.when.apply($, promises).then(function () {
                    success(events);
                }).fail(function () {
                    failure('Gagal memuat ketersediaan.');
                });
            },
            dayCellDidMount: function (info) {
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                var cellDate = new Date(info.date);
                cellDate.setHours(0, 0, 0, 0);

                if (cellDate < today) {
                    info.el.classList.add('tsrb-date-past');
                } else {
                    info.el.classList.add('tsrb-date-future');
                }
            },
            dateClick: function () {}
        });

        calendar.render();

        $('#tsrb-public-studio-id').on('change', function () {
            calendar.refetchEvents();
        });
    }

    // ================== AUTH MODAL (LOGIN & REGISTER) ==================

    function openAuthModal(target) {
        $('#tsrb-auth-modal').show();

        if (target === 'register') {
            $('.tsrb-auth-tab-login').removeClass('tsrb-auth-tab-active');
            $('.tsrb-auth-tab-register').addClass('tsrb-auth-tab-active');
            $('.tsrb-auth-tab-panel-login').removeClass('tsrb-auth-tab-panel-active');
            $('.tsrb-auth-tab-panel-register').addClass('tsrb-auth-tab-panel-active');
        } else {
            $('.tsrb-auth-tab-register').removeClass('tsrb-auth-tab-active');
            $('.tsrb-auth-tab-login').addClass('tsrb-auth-tab-active');
            $('.tsrb-auth-tab-panel-register').removeClass('tsrb-auth-tab-panel-active');
            $('.tsrb-auth-tab-panel-login').addClass('tsrb-auth-tab-panel-active');
        }

        $('#tsrb-login-message, #tsrb-register-message').text('').css('color', '');

        setTimeout(function () {
            if (target === 'register') {
                $('#tsrb-reg-username').trigger('focus');
            } else {
                $('#tsrb-login-username').trigger('focus');
            }
        }, 50);
    }

    function closeAuthModal() {
        $('#tsrb-auth-modal').hide();
    }

    $(document).on('click', '.tsrb-open-login-modal', function () {
        openAuthModal('login');
    });

    $(document).on('click', '.tsrb-open-register-modal', function () {
        openAuthModal('register');
    });

    $(document).on('click', '.tsrb-auth-modal-close, .tsrb-auth-modal-overlay', function () {
        closeAuthModal();
    });

    $(document).on('click', '.tsrb-auth-tab-login', function () {
        openAuthModal('login');
    });

    $(document).on('click', '.tsrb-auth-tab-register', function () {
        openAuthModal('register');
    });

    $(document).on('click', '.tsrb-switch-to-register', function (e) {
        e.preventDefault();
        openAuthModal('register');
    });

    $(document).on('click', '.tsrb-switch-to-login', function (e) {
        e.preventDefault();
        openAuthModal('login');
    });

    // ================== ACCOUNT MODALS (HISTORY & PROFILE) ==================

    function openHistoryModal() {
        $('#tsrb-history-modal').show();
        $('#tsrb-history-modal-body').html('<p class="tsrb-info">Memuat riwayat booking...</p>');

        $.ajax({
            url: TSRB_Public.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'tsrb_get_user_bookings',
                nonce:  TSRB_Public.nonce
            }
        }).done(function (response) {
            if (!response || !response.success) {
                $('#tsrb-history-modal-body').html(
                    '<p class="tsrb-error">' +
                    (response && response.data && response.data.message ? response.data.message : 'Gagal memuat riwayat booking.') +
                    '</p>'
                );
                return;
            }
            $('#tsrb-history-modal-body').html(response.data && response.data.html ? response.data.html : '');
            initFrontendPaymentTimers();
        }).fail(function () {
            $('#tsrb-history-modal-body').html('<p class="tsrb-error">Gagal memuat riwayat booking.</p>');
        });
    }

    function openProfileModal() {
        $('#tsrb-profile-modal').show();
        $('#tsrb-profile-modal-body').html('<p class="tsrb-info">Memuat profil...</p>');

        $.ajax({
            url: TSRB_Public.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'tsrb_get_user_profile',
                nonce:  TSRB_Public.nonce
            }
        }).done(function (response) {
            if (!response || !response.success) {
                $('#tsrb-profile-modal-body').html(
                    '<p class="tsrb-error">' +
                    (response && response.data && response.data.message ? response.data.message : 'Gagal memuat profil.') +
                    '</p>'
                );
                return;
            }
            $('#tsrb-profile-modal-body').html(response.data && response.data.html ? response.data.html : '');
        }).fail(function () {
            $('#tsrb-profile-modal-body').html('<p class="tsrb-error">Gagal memuat profil.</p>');
        });
    }

    function closeAccountModals() {
        $('#tsrb-history-modal, #tsrb-profile-modal').hide();
    }

    $(document).on('click', '.tsrb-account-history-trigger', function () {
        if (!isLoggedInFrontend()) {
            openAuthModal('login');
            return;
        }
        openHistoryModal();
    });

    $(document).on('click', '.tsrb-account-profile-trigger', function () {
        if (!isLoggedInFrontend()) {
            openAuthModal('login');
            return;
        }
        openProfileModal();
    });

    $(document).on(
        'click',
        '#tsrb-history-modal .tsrb-account-modal-close, #tsrb-history-modal .tsrb-account-modal-overlay,' +
        '#tsrb-profile-modal .tsrb-account-modal-close, #tsrb-profile-modal .tsrb-account-modal-overlay',
        function () {
            closeAccountModals();
        }
    );

    // ================== PROFILE & PASSWORD AJAX ==================

    $(document).on('submit', '#tsrb-profile-form', function (e) {
        e.preventDefault();

        var $form   = $(this);
        var $notice = $form.find('.tsrb-profile-inline-notice');
        if ($notice.length === 0) {
            $notice = $('<div class="tsrb-profile-inline-notice"></div>').insertBefore($form.find('.tsrb-actions'));
        }

        var data = $form.serializeArray();
        data.push({ name: 'nonce', value: TSRB_Public.nonce });
        data.push({ name: 'action', value: 'tsrb_update_user_profile' });

        $notice.removeClass('tsrb-error tsrb-success').text('Menyimpan profil...');

        $.ajax({
            url: TSRB_Public.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: $.param(data)
        }).done(function (response) {
            if (!response || typeof response.success === 'undefined') {
                $notice.addClass('tsrb-error').text('Terjadi kesalahan. Silakan coba lagi.');
                return;
            }
            if (!response.success) {
                $notice.addClass('tsrb-error').text(
                    response.data && response.data.message ? response.data.message : 'Gagal menyimpan profil.'
                );
                return;
            }
            $notice.addClass('tsrb-success').text(
                response.data && response.data.message ? response.data.message : 'Profil berhasil disimpan.'
            );
        }).fail(function () {
            $notice.addClass('tsrb-error').text('Terjadi kesalahan koneksi.');
        });
    });

    $(document).on('submit', '#tsrb-password-form', function (e) {
        e.preventDefault();

        var $form   = $(this);
        var $notice = $form.find('.tsrb-profile-inline-notice');
        if ($notice.length === 0) {
            $notice = $('<div class="tsrb-profile-inline-notice"></div>').insertBefore($form.find('.tsrb-actions'));
        }

        var data = $form.serializeArray();
        data.push({ name: 'nonce', value: TSRB_Public.nonce });
        data.push({ name: 'action', value: 'tsrb_change_password' });

        $notice.removeClass('tsrb-error tsrb-success').text('Memperbarui password...');

        $.ajax({
            url: TSRB_Public.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: $.param(data)
        }).done(function (response) {
            if (!response || typeof response.success === 'undefined') {
                $notice.addClass('tsrb-error').text('Terjadi kesalahan. Silakan coba lagi.');
                return;
            }
            if (!response.success) {
                $notice.addClass('tsrb-error').text(
                    response.data && response.data.message ? response.data.message : 'Gagal mengubah password.'
                );
                return;
            }
            $notice.addClass('tsrb-success').text(
                response.data && response.data.message ? response.data.message : 'Password berhasil diubah.'
            );
            $form[0].reset();
        }).fail(function () {
            $notice.addClass('tsrb-error').text('Terjadi kesalahan koneksi.');
        });
    });

    // ================== APPLY LOGGED-IN USER TO UI ==================

    function applyLoggedInUserToUI(user) {
        if (!user) {
            return;
        }

        $('.tsrb-booking-wrapper').data('tsrb-logged-in', 1);

        var $activeStep = $('.tsrb-step.tsrb-step-active');
        var currentStep = $activeStep.length ? parseInt($activeStep.data('step'), 10) : 1;

        var $panel2 = $('.tsrb-step-panel-2');
        if ($panel2.length) {
            $panel2.find('.tsrb-data-login-notice, .tsrb-data-auth-buttons, .tsrb-login-notice, .tsrb-auth-buttons').hide();

            if ($panel2.find('.tsrb-data-form').length === 0) {
                var formHtml =
                    '<p class="tsrb-info tsrb-data-info">' +
                        'Data di bawah ini akan digunakan untuk booking Anda. Anda bisa mengubahnya terlebih dahulu jika diperlukan.' +
                    '</p>' +
                    '<div class="tsrb-data-form">' +
                        '<p><label for="tsrb-full-name">Nama Lengkap *</label><br>' +
                        '<input type="text" id="tsrb-full-name" name="full_name" required></p>' +
                        '<p><label for="tsrb-email">Email *</label><br>' +
                        '<input type="email" id="tsrb-email" name="email" required></p>' +
                        '<p><label for="tsrb-phone">No. HP / WhatsApp *</label><br>' +
                        '<input type="text" id="tsrb-phone" name="phone" required></p>' +
                        '<p><label for="tsrb-notes">Catatan Tambahan (opsional)</label><br>' +
                        '<textarea id="tsrb-notes" name="notes" rows="4"></textarea></p>' +
                    '</div>';
                $panel2.find('h3').first().after(formHtml);
            }

            if (user.display_name) { $('#tsrb-full-name').val(user.display_name); }
            if (user.email)        { $('#tsrb-email').val(user.email); }
            if (user.phone)        { $('#tsrb-phone').val(user.phone); }

            var $actions = $panel2.find('.tsrb-step-actions');
            if ($actions.length && $actions.find('.tsrb-next-step').length === 0) {
                $('<button>', {
                    type: 'button',
                    class: 'tsrb-next-step tsrb-btn tsrb-btn-primary button button-primary',
                    'data-next-step': 3,
                    text: 'Lanjut: Konfirmasi'
                }).appendTo($actions);
            }
        }

        var $status = $('.tsrb-account-status');
        if ($status.length) {
            var initials = '';
            if (user.display_name) {
                var parts = user.display_name.trim().split(/\s+/);
                if (parts.length > 0) {
                    var first = parts[0].charAt(0);
                    var last  = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
                    initials  = (first + last).toUpperCase();
                }
            }

            $status.find('.tsrb-account-avatar')
                .removeClass('tsrb-account-avatar-guest')
                .text(initials || '?');

            $status.find('.tsrb-account-status-line').html(
                'Login sebagai <strong>' + (user.display_name || '') + '</strong>'
            );
            $status.find('.tsrb-account-status-sub').text(user.email || '');

            var logoutUrl = $status.data('logout-url');

            var $right = $status.find('.tsrb-account-status-right');
            if (!$right.length) {
                $right = $('<div>', { class: 'tsrb-account-status-right' }).appendTo($status);
            }
            $right.empty()
                .append($('<button>', { type: 'button', class: 'tsrb-account-status-link tsrb-account-history-trigger', text: 'Riwayat Booking' }))
                .append($('<button>', { type: 'button', class: 'tsrb-account-status-link tsrb-account-profile-trigger', text: 'Profil Saya' }))
                .append($('<button>', { type: 'button', class: 'tsrb-account-status-link tsrb-account-logout-trigger', text: 'Logout' }));

            $(document).off('click.tsrbLogout', '.tsrb-account-logout-trigger');
            $(document).on('click.tsrbLogout', '.tsrb-account-logout-trigger', function () {
                if (logoutUrl) {
                    window.location.href = logoutUrl;
                } else {
                    window.location.reload();
                }
            });
        }

        goToStep(currentStep);

        // Refresh nonce setelah login/register, lalu reload slot jika tanggal sudah dipilih.
        $.ajax({
            url: TSRB_Public.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: { action: 'tsrb_refresh_public_nonce' }
        }).done(function (res) {
            if (res && res.success && res.data && res.data.nonce) {
                TSRB_Public.nonce = res.data.nonce;
            }

            var selectedDateStr = $('#tsrb-selected-date-text').data('value');
            if (selectedDateStr) {
                loadSlotsForDate(selectedDateStr);
            } else {
                $('#tsrb-time-slots').html('<p class="tsrb-info">Silakan pilih tanggal untuk melihat jam yang tersedia.</p>');
            }
        }).fail(function () {
            var selectedDateStr = $('#tsrb-selected-date-text').data('value');
            if (selectedDateStr) {
                loadSlotsForDate(selectedDateStr);
            }
        });
    }

    // ================== REGISTER ==================

    $(document).on('click', '#tsrb-register-submit', function () {
        var payload = {
            action:           'tsrb_register_user',
            nonce:            TSRB_Public.nonce,
            username:         $('#tsrb-reg-username').val(),
            full_name:        $('#tsrb-reg-fullname').val(),
            email:            $('#tsrb-reg-email').val(),
            phone:            $('#tsrb-reg-phone').val(),
            password:         $('#tsrb-reg-password').val(),
            password_confirm: $('#tsrb-reg-password-confirm').val()
        };

        $('#tsrb-register-message').css('color', '').text('Mendaftarkan akun...');

        $.ajax({
            url:      TSRB_Public.ajax_url,
            type:     'POST',
            dataType: 'json',
            data:     payload
        }).done(function (response) {
            if (!response || typeof response.success === 'undefined') {
                $('#tsrb-register-message').css('color', 'red').text('Terjadi kesalahan. Silakan coba lagi.');
                return;
            }
            if (!response.success) {
                var msg = (response.data && response.data.message) ? response.data.message : 'Pendaftaran gagal.';
                $('#tsrb-register-message').css('color', 'red').text(msg);
                return;
            }

            var user = response.data && response.data.user ? response.data.user : null;
            $('#tsrb-register-message')
                .css('color', 'green')
                .text(response.data.message || 'Pendaftaran berhasil. Anda sudah login.');

            if (user) {
                applyLoggedInUserToUI(user);
            }

            setTimeout(function () {
                closeAuthModal();
            }, 600);
        }).fail(function (xhr) {
            var msg = 'Terjadi kesalahan koneksi.';
            if (xhr && xhr.responseJSON && typeof xhr.responseJSON === 'object') {
                if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                } else if (xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
            }
            $('#tsrb-register-message').css('color', 'red').text(msg);
        });
    });

    // ================== LOGIN ==================

    $(document).on('submit', '#tsrb-login-form', function (e) {
        e.preventDefault();

        var data = {
            action:      'tsrb_login_user',
            nonce:       TSRB_Public.nonce,
            log:         $('#tsrb-login-username').val(),
            pwd:         $('#tsrb-login-password').val(),
            remember_me: $('#tsrb-login-remember').is(':checked') ? 1 : 0
        };

        $('#tsrb-login-message').css('color', '').text('Memproses login...');

        $.ajax({
            url:      TSRB_Public.ajax_url,
            type:     'POST',
            dataType: 'json',
            data:     data
        }).done(function (response) {
            if (!response || typeof response.success === 'undefined' || !response.success) {
                var msg = 'Login gagal.';
                if (response && response.data && response.data.message) {
                    msg = response.data.message;
                }
                $('#tsrb-login-message').css('color', 'red').text(msg);
                return;
            }

            var user = response.data && response.data.user ? response.data.user : null;
            $('#tsrb-login-message').css('color', 'green').text(response.data.message || 'Login berhasil.');

            if (user) {
                applyLoggedInUserToUI(user);
            }

            closeAuthModal();

            setTimeout(function () {
                var $wrapper = $('.tsrb-booking-wrapper');
                if ($wrapper.length) {
                    $('html, body').animate({ scrollTop: $wrapper.offset().top - 20 }, 300);
                    var $name = $('#tsrb-full-name');
                    if ($name.length) { $name.trigger('focus'); }
                }
            }, 300);
        }).fail(function (xhr) {
            var msg = 'Terjadi kesalahan koneksi.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = xhr.responseJSON.data.message;
            }
            $('#tsrb-login-message').css('color', 'red').text(msg);
        });
    });

    $(document).on('click', '#tsrb-login-submit', function () {
        $('#tsrb-login-form').trigger('submit');
    });

    // ================== MEMBER CANCELLATION REQUEST (DASHBOARD) ==================

    $(document).on('click', '.tsrb-btn-cancel-request', function () {
        var bookingId = $(this).data('tsrb-cancel-booking-id');
        if (!bookingId) { return; }

        var $row = $('.tsrb-user-booking-row[data-tsrb-booking-id="' + bookingId + '"]').first();
        var summaryText = '';

        if ($row.length) {
            var date   = $row.data('tsrb-booking-date') || $row.find('.tsrb-user-booking-date').text();
            var time   = $row.data('tsrb-booking-start') && $row.data('tsrb-booking-end')
                ? ($row.data('tsrb-booking-start') + ' - ' + $row.data('tsrb-booking-end'))
                : $row.find('.tsrb-user-booking-time').text();
            var studio = $row.data('tsrb-booking-studio') || $row.find('.tsrb-user-booking-studio').text();
            summaryText = studio + ' — ' + date + ' (' + time + ')';
        }

        var $modal = $('.tsrb-modal-cancel-request');
        if (!$modal.length) { return; }

        $modal.data('tsrb-cancel-booking-id', bookingId);
        $modal.find('.tsrb-modal-cancel-booking-summary').text(summaryText);
        $modal.find('#tsrb-cancel-request-note').val('');
        $modal.find('.tsrb-modal-cancel-error').hide().text('');
        $modal.find('.tsrb-modal-cancel-success').hide().text('');

        $modal.show();
    });

    $(document).on(
        'click',
        '.tsrb-modal-cancel-request .tsrb-modal-close,' +
        ' .tsrb-modal-cancel-request .tsrb-modal-cancel,' +
        ' .tsrb-modal-cancel-request .tsrb-modal-backdrop',
        function () {
            $('.tsrb-modal-cancel-request').hide();
        }
    );

    $(document).on('click', '.tsrb-modal-submit-cancel-request', function () {
        var $modal      = $('.tsrb-modal-cancel-request');
        var bookingId   = $modal.data('tsrb-cancel-booking-id');
        var note        = $('#tsrb-cancel-request-note').val() || '';
        var $errorBox   = $modal.find('.tsrb-modal-cancel-error');
        var $successBox = $modal.find('.tsrb-modal-cancel-success');

        $errorBox.hide().text('');
        $successBox.hide().text('');

        if (!bookingId) {
            $errorBox.text('ID booking tidak ditemukan.').show();
            return;
        }

        $successBox.text('Mengirim pengajuan pembatalan...').show();

        $.ajax({
            url: TSRB_Public.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action:     'tsrb_member_request_cancellation',
                nonce:      TSRB_Public.nonce,
                booking_id: bookingId,
                note:       note
            }
        }).done(function (response) {
            $successBox.hide();
            if (!response || !response.success) {
                $errorBox.text(
                    response && response.data && response.data.message
                        ? response.data.message
                        : 'Gagal mengirim pengajuan pembatalan.'
                ).show();
                return;
            }

            $successBox.text(
                response.data && response.data.message
                    ? response.data.message
                    : 'Pengajuan pembatalan berhasil dikirim.'
            ).show();

            var $row = $('.tsrb-user-booking-row[data-tsrb-booking-id="' + bookingId + '"]');
            if ($row.length) {
                $row.removeClass(function (idx, cls) {
                    return (cls || '').split(' ')
                        .filter(function (c) { return c.indexOf('tsrb-user-booking-row-status-') === 0; })
                        .join(' ');
                }).addClass('tsrb-user-booking-row-status-cancel_requested');

                $row.find('.tsrb-user-booking-status').text('Pengajuan Pembatalan');
                $row.find('.tsrb-btn-cancel-request').remove();
                $row.find('.tsrb-user-booking-actions').each(function () {
                    var $actions = $(this);
                    if (!$actions.find('.tsrb-user-booking-cancel-requested-label').length) {
                        $('<span>', {
                            class: 'tsrb-user-booking-cancel-requested-label',
                            text:  'Sedang diproses admin'
                        }).appendTo($actions);
                    }
                });
            }

            setTimeout(function () { $modal.hide(); }, 1200);
        }).fail(function () {
            $successBox.hide();
            $errorBox.text('Terjadi kesalahan koneksi.').show();
        });
    });

    // ================== INIT ==================

    initBookingCalendar();
    initPublicCalendar();

    if (initialUser) {
        applyLoggedInUserToUI(initialUser);
    }

});