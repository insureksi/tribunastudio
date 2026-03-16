jQuery(document).ready(function ($) {
    'use strict';

    /** ================= Dashboard: Quick metrics range switch ================= */
    $(document).on('click', '.tsrb-range-switch button', function (e) {
        e.preventDefault();

        var range = $(this).data('range');

        $('.tsrb-range-switch button')
            .removeClass('button-primary')
            .addClass('button-secondary');

        $(this)
            .removeClass('button-secondary')
            .addClass('button-primary');

        $('.tsrb-range-metrics').hide();
        $('.tsrb-range-metrics[data-range="' + range + '"]').show();
    });

    /** ================= Dashboard FullCalendar ================= */
    var $calendarEl = $('#tsrb-admin-calendar');

    if ($calendarEl.length && typeof FullCalendar !== 'undefined') {
        var calendar = new FullCalendar.Calendar($calendarEl[0], {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            events: function (info, successCallback, failureCallback) {
                $.ajax({
                    url: TSRBAdmin.ajaxurl,
                    data: {
                        action: 'tsrb_get_admin_calendar_events',
                        nonce: TSRBAdmin.nonce,
                        start: info.startStr,
                        end: info.endStr
                    },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        if ($.isArray(response.data)) {
                            successCallback(response.data);
                        } else if (response.data && $.isArray(response.data.events)) {
                            successCallback(response.data.events);
                        } else {
                            successCallback([]);
                        }
                    } else {
                        failureCallback(response.data && response.data.message ? response.data.message : 'Error');
                    }
                }).fail(function () {
                    failureCallback('Error');
                });
            },
            eventClick: function (info) {
                info.jsEvent.preventDefault();

                var event = info.event;

                if (event.display === 'background' || (event.extendedProps && event.extendedProps.isBusy)) {
                    return;
                }

                var $modal = $('#tsrb-calendar-modal');
                var $body  = $modal.find('.tsrb-calendar-modal-details');

                var html = '';

                html += '<p><strong>ID:</strong> ' + event.id + '</p>';

                if (event.title) {
                    html += '<p><strong>Title:</strong> ' + event.title + '</p>';
                }

                if (event.start) {
                    html += '<p><strong>Start:</strong> ' + event.start.toLocaleString() + '</p>';
                }
                if (event.end) {
                    html += '<p><strong>End:</strong> ' + event.end.toLocaleString() + '</p>';
                }

                if (event.extendedProps && event.extendedProps.status) {
                    html += '<p><strong>Status:</strong> ' + event.extendedProps.status + '</p>';
                }
                if (event.extendedProps && event.extendedProps.customer) {
                    html += '<p><strong>Customer:</strong> ' + event.extendedProps.customer + '</p>';
                }

                $body.html(html);
                $modal.show();
            }
        });

        calendar.render();
    }

    /** ================= Calendar modal close ================= */
    $(document).on('click', '.tsrb-calendar-modal-close', function () {
        $('#tsrb-calendar-modal').hide();
    });

    $(document).on('click', '#tsrb-calendar-modal', function (e) {
        if ($(e.target).attr('id') === 'tsrb-calendar-modal') {
            $('#tsrb-calendar-modal').hide();
        }
    });

    /** ================= Booking edit: admin reschedule calendar + slots ================= */

    var adminCalendarEl = document.getElementById('tsrb-admin-booking-calendar');
    var tsrbSelectedRescheduleDate = null; // tanggal yang dipilih di kalender reschedule

    function tsrbInitAdminRescheduleCalendar() {
        if (!adminCalendarEl || typeof FullCalendar === 'undefined') {
            return;
        }

        var $wrap = $('.tsrb-admin-reschedule-wrapper');
        if (!$wrap.length) {
            return;
        }

        var studioId    = $wrap.data('studio-id') || 0;
        var bookingId   = $wrap.data('booking-id') || 0;
        var initialDate = $('#tsrb-reschedule-date').val();

        var calendar = new FullCalendar.Calendar(adminCalendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            validRange: function () {
                var now   = new Date();
                var yyyy  = now.getFullYear();
                var mm    = ('0' + (now.getMonth() + 1)).slice(-2);
                var dd    = ('0' + now.getDate()).slice(-2);
                var today = yyyy + '-' + mm + '-' + dd;
                return { start: today };
            },
            dateClick: function (info) {
                var dateStr = info.dateStr;

                tsrbSelectedRescheduleDate = dateStr;

                $('#tsrb-reschedule-date').val(dateStr);

                $(adminCalendarEl).find('.fc-daygrid-day').removeClass('tsrb-selected-day');
                if (info.dayEl) {
                    info.dayEl.classList.add('tsrb-selected-day');
                }

                $('#tsrb-reschedule-selected-date-info').text(dateStr);

                tsrbAdminLoadSlotsForDate(dateStr, studioId, bookingId);
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

                if (tsrbSelectedRescheduleDate) {
                    var d = info.date;
                    var yyyy = d.getFullYear();
                    var mm   = ('0' + (d.getMonth() + 1)).slice(-2);
                    var dd   = ('0' + d.getDate()).slice(-2);
                    var cellStr = yyyy + '-' + mm + '-' + dd;

                    if (cellStr === tsrbSelectedRescheduleDate) {
                        info.el.classList.add('tsrb-selected-day');
                    }
                }
            }
        });

        calendar.render();

        if (initialDate) {
            tsrbSelectedRescheduleDate = initialDate;
            $('#tsrb-reschedule-selected-date-info').text(initialDate);
            tsrbAdminLoadSlotsForDate(initialDate, studioId, bookingId);
        }
    }

    function tsrbAdminLoadSlotsForDate(dateStr, studioId, bookingId) {
        var $slots = $('#tsrb-admin-time-slots');
        $slots.html('<p class="tsrb-info">Memuat jam tersedia...</p>');

        $.ajax({
            url: TSRBAdmin.ajaxurl,
            dataType: 'json',
            data: {
                action: 'tsrb_get_admin_availability',
                nonce: TSRBAdmin.nonce,
                date: dateStr,
                studio_id: studioId,
                booking_id: bookingId
            }
        }).done(function (response) {
            if (!response || !response.success) {
                var msg = (response && response.data && response.data.message)
                    ? response.data.message
                    : 'Terjadi kesalahan saat memuat jam.';
                $slots.html('<p class="tsrb-error">' + msg + '</p>');
                $('#tsrb-admin-slot-start, #tsrb-admin-slot-end').val('');
                $('#tsrb-reschedule-start, #tsrb-reschedule-end').val('');
                return;
            }

            tsrbAdminRenderSlots(response.data);
        }).fail(function () {
            $slots.html('<p class="tsrb-error">Gagal memuat jam tersedia.</p>');
            $('#tsrb-admin-slot-start, #tsrb-admin-slot-end').val('');
            $('#tsrb-reschedule-start, #tsrb-reschedule-end').val('');
        });
    }

    function tsrbAdminRenderSlots(data) {
        var slots  = data.slots || [];
        var $slots = $('#tsrb-admin-time-slots');

        if (!slots.length) {
            $slots.html('<p class="tsrb-info">Tidak ada jam tersedia di tanggal ini.</p>');
            $('#tsrb-admin-slot-start, #tsrb-admin-slot-end').val('');
            $('#tsrb-reschedule-start, #tsrb-reschedule-end').val('');
            return;
        }

        var html = '<div class="tsrb-slots-list">';
        slots.forEach(function (slot, index) {
            var start = slot.start_time || slot.starttime;
            var end   = slot.end_time || slot.endtime;

            var label = start.substring(0, 5) + ' - ' + end.substring(0, 5);
            var statusLabel = (slot.status === 'booked') ? 'BOOKED' : 'AVAILABLE';
            var cssClass = 'tsrb-slot-item ' + (slot.status === 'booked' ? 'tsrb-slot-booked' : 'tsrb-slot-available');

            html += '<div class="' + cssClass + '" data-index="' + index + '" data-start="' + start + '" data-end="' + end + '">';
            html += '  <div class="tsrb-slot-time">' + label + '</div>';
            html += '  <div class="tsrb-slot-status">' + statusLabel + '</div>';
            html += '</div>';
        });
        html += '</div>';

        $slots.html(html);

        $('.tsrb-slot-available').on('click', function () {
            var $this = $(this);

            if ($this.hasClass('tsrb-slot-selected')) {
                $('.tsrb-slot-available').removeClass('tsrb-slot-selected');
                $('#tsrb-admin-slot-start, #tsrb-admin-slot-end').val('');
                $('#tsrb-reschedule-start, #tsrb-reschedule-end').val('');
                return;
            }

            var selected = $('.tsrb-slot-selected');
            if (!selected.length) {
                $this.addClass('tsrb-slot-selected');
            } else {
                var firstIndex = parseInt(selected.first().data('index'), 10);
                var lastIndex  = parseInt(selected.last().data('index'), 10);
                var currentIdx = parseInt($this.data('index'), 10);

                if (currentIdx === lastIndex + 1 || currentIdx === firstIndex - 1) {
                    $this.addClass('tsrb-slot-selected');
                } else {
                    $('.tsrb-slot-available').removeClass('tsrb-slot-selected');
                    $this.addClass('tsrb-slot-selected');
                }
            }

            var range = $('.tsrb-slot-selected').sort(function (a, b) {
                return parseInt($(a).data('index'), 10) - parseInt($(b).data('index'), 10);
            });

            var startTime = range.first().data('start');
            var endTime   = range.last().data('end');

            $('#tsrb-admin-slot-start').val(startTime);
            $('#tsrb-admin-slot-end').val(endTime);

            $('#tsrb-reschedule-start').val(startTime.substring(0, 5));
            $('#tsrb-reschedule-end').val(endTime.substring(0, 5));
        });
    }

    if (adminCalendarEl) {
        tsrbInitAdminRescheduleCalendar();
    }

    /** ================= Booking edit: save status via AJAX ================= */
    $('#tsrb-save-booking').on('click', function (e) {
        e.preventDefault();

        var $btn      = $(this);
        var bookingId = $btn.data('booking-id');
        var status    = $('#tsrb-booking-status').val();
        var adminNote = $('#tsrb-admin-note').val();

        $('.tsrb-save-status').text('');

        $.ajax({
            url: TSRBAdmin.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'tsrb_update_booking_status',
                nonce: TSRBAdmin.nonce,
                booking_id: bookingId,
                status: status,
                admin_note: adminNote
            }
        }).done(function (response) {
            if (response.success) {
                $('.tsrb-save-status').text(response.data.message).css('color', 'green');
            } else {
                $('.tsrb-save-status').text(response.data.message || 'Error').css('color', 'red');
            }
        }).fail(function () {
            $('.tsrb-save-status').text('Error').css('color', 'red');
        });
    });

    /** ================= Booking edit: admin reschedule via AJAX ================= */
    $('#tsrb-admin-reschedule-booking').on('click', function (e) {
        e.preventDefault();

        var $btn      = $(this);
        var bookingId = $btn.data('booking-id');

        var newDate  = $('#tsrb-reschedule-date').val();
        var newStart = $('#tsrb-reschedule-start').val();
        var newEnd   = $('#tsrb-reschedule-end').val();

        var $statusEl = $('.tsrb-reschedule-status');
        $statusEl.text('').css('color', '');

        if (!bookingId || !newDate || !newStart || !newEnd) {
            $statusEl.text(TSRBAdmin.rescheduleincomplete || 'Please fill all new schedule fields.').css('color', 'red');
            return;
        }

        $btn.prop('disabled', true);
        $statusEl.text(TSRBAdmin.rescheduleprocessing || 'Saving new schedule...').css('color', '');

        $.ajax({
            url: TSRBAdmin.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'tsrb_admin_reschedule_booking',
                nonce: TSRBAdmin.nonce,
                booking_id: bookingId,
                new_date: newDate,
                new_start: newStart,
                new_end: newEnd
            }
        }).done(function (response) {
            if (response.success && response.data) {
                $statusEl.text(
                    response.data.message || (TSRBAdmin.reschedulesuccess || 'Schedule updated.')
                ).css('color', 'green');

                if (response.data.date) {
                    $('.tsrb-booking-main .form-table tr').each(function () {
                        var $th = $(this).find('th');
                        if ($th.length && $th.text().toLowerCase().indexOf('date') !== -1) {
                            $(this).find('td').text(response.data.date);
                        }
                    });
                }
                if (response.data.start_time && response.data.end_time) {
                    $('.tsrb-booking-main .form-table tr').each(function () {
                        var $th = $(this).find('th');
                        if ($th.length && $th.text().toLowerCase().indexOf('time') !== -1) {
                            $(this).find('td').text(
                                response.data.start_time.substring(0, 5) +
                                ' - ' +
                                response.data.end_time.substring(0, 5)
                            );
                        }
                    });
                }
            } else {
                var msg = (response.data && response.data.message)
                    ? response.data.message
                    : (TSRBAdmin.rescheduleerror || 'Failed to update schedule.');
                $statusEl.text(msg).css('color', 'red');
            }
        }).fail(function () {
            $statusEl.text(TSRBAdmin.rescheduleerror || 'Failed to update schedule.').css('color', 'red');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    /** ================= Booking edit: Cancellation & Refund (approve / reject / direct) ================= */

    function tsrbAdminProcessCancellation(options) {
        var bookingId   = options.bookingId;
        var actionType  = options.actionType; // approve / reject / direct
        var adminNote   = options.adminNote || '';
        var $statusEl   = options.$statusEl;
        var $btns       = options.$btns || $();

        if (!bookingId || !actionType) {
            return;
        }

        var confirmText;

        if (actionType === 'approve') {
            confirmText = TSRBAdmin.cancelApproveConfirm || 'Approve this cancellation and apply refund/credit?';
        } else if (actionType === 'reject') {
            confirmText = TSRBAdmin.cancelRejectConfirm || 'Reject this cancellation request?';
        } else {
            confirmText = TSRBAdmin.cancelConfirm || 'Are you sure you want to cancel this booking?';
        }

        if (!window.confirm(confirmText)) {
            return;
        }

        if ($statusEl && $statusEl.length) {
            $statusEl.text(TSRBAdmin.cancelProcessing || 'Processing cancellation...').css('color', '');
        }

        $btns.prop('disabled', true);

        $.ajax({
            url: TSRBAdmin.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'tsrb_admin_process_cancellation',
                nonce: TSRBAdmin.nonce,
                booking_id: bookingId,
                process_action: actionType,
                admin_note: adminNote
            }
        }).done(function (response) {
            if (!response || !response.success) {
                var msg = (response && response.data && response.data.message)
                    ? response.data.message
                    : (TSRBAdmin.cancelError || 'Failed to process cancellation.');

                if ($statusEl && $statusEl.length) {
                    $statusEl.text(msg).css('color', 'red');
                }
                return;
            }

            var data = response.data || {};
            var msg  = data.message || (TSRBAdmin.cancelSuccess || 'Cancellation processed.');

            if ($statusEl && $statusEl.length) {
                $statusEl.text(msg).css('color', 'green');
            }

            // Update status dropdown jika ada.
            if (data.new_status) {
                $('#tsrb-booking-status').val(data.new_status);

                var statusLabel = data.new_status.replace(/_/g, ' ');
                $('.tsrb-booking-status-inline-wrapper select[data-booking-id="' + bookingId + '"]').val(data.new_status);

                // Update row di list jika ada.
                var $row = $('.tsrb-bookings-table').find('tr').filter(function () {
                    return $(this).find('input.tsrb-booking-checkbox').val() == bookingId;
                });

                if ($row.length) {
                    $row.find('.tsrb-booking-status-select').val(data.new_status);

                    $row.removeClass('tsrb-status-pending tsrb-status-paid tsrb-status-cancelled tsrb-status-cancel-requested');
                    if (data.new_status === 'pending_payment') {
                        $row.addClass('tsrb-status-pending');
                    } else if (data.new_status === 'paid') {
                        $row.addClass('tsrb-status-paid');
                    } else if (data.new_status === 'cancel_requested') {
                        $row.addClass('tsrb-status-cancel-requested');
                    } else if (data.new_status === 'cancelled') {
                        $row.addClass('tsrb-status-cancelled');
                    }
                }
            }

            // Setelah proses, bisa reload halaman jika diinstruksikan dari server.
            if (data.reload) {
                window.setTimeout(function () {
                    window.location.reload();
                }, 800);
            }
        }).fail(function () {
            if ($statusEl && $statusEl.length) {
                $statusEl.text(TSRBAdmin.cancelError || 'Failed to process cancellation.').css('color', 'red');
            }
        }).always(function () {
            $btns.prop('disabled', false);
        });
    }

    // Edit page: approve cancellation request.
    $(document).on('click', '.tsrb-approve-cancellation', function (e) {
        e.preventDefault();

        var bookingId = $(this).data('booking-id');
        var adminNote = $('#tsrb-cancel-admin-note').val() || '';
        var $statusEl = $('.tsrb-cancel-status');
        var $btns     = $('.tsrb-approve-cancellation, .tsrb-reject-cancellation');

        tsrbAdminProcessCancellation({
            bookingId: bookingId,
            actionType: 'approve',
            adminNote: adminNote,
            $statusEl: $statusEl,
            $btns: $btns
        });
    });

    // Edit page: reject cancellation request.
    $(document).on('click', '.tsrb-reject-cancellation', function (e) {
        e.preventDefault();

        var bookingId = $(this).data('booking-id');
        var adminNote = $('#tsrb-cancel-admin-note').val() || '';
        var $statusEl = $('.tsrb-cancel-status');
        var $btns     = $('.tsrb-approve-cancellation, .tsrb-reject-cancellation');

        tsrbAdminProcessCancellation({
            bookingId: bookingId,
            actionType: 'reject',
            adminNote: adminNote,
            $statusEl: $statusEl,
            $btns: $btns
        });
    });

    // Edit page: direct cancellation by admin (no request from member).
    $(document).on('click', '.tsrb-direct-cancel-booking', function (e) {
        e.preventDefault();

        var bookingId = $(this).data('booking-id');
        var adminNote = $('#tsrb-cancel-admin-note-direct').val() || '';
        var $statusEl = $('.tsrb-cancel-status');
        var $btns     = $('.tsrb-direct-cancel-booking');

        tsrbAdminProcessCancellation({
            bookingId: bookingId,
            actionType: 'direct',
            adminNote: adminNote,
            $statusEl: $statusEl,
            $btns: $btns
        });
    });

    // List page: approve cancellation from row.
    $(document).on('click', '.tsrb-list-approve-cancel', function (e) {
        e.preventDefault();

        var bookingId = $(this).data('booking-id');
        var $row      = $(this).closest('tr');
        var $statusEl = $row.find('.tsrb-inline-status-msg[data-booking-id="' + bookingId + '"]');
        var $btns     = $row.find('.tsrb-list-approve-cancel, .tsrb-list-reject-cancel');

        tsrbAdminProcessCancellation({
            bookingId: bookingId,
            actionType: 'approve',
            adminNote: '',
            $statusEl: $statusEl,
            $btns: $btns
        });
    });

    // List page: reject cancellation from row.
    $(document).on('click', '.tsrb-list-reject-cancel', function (e) {
        e.preventDefault();

        var bookingId = $(this).data('booking-id');
        var $row      = $(this).closest('tr');
        var $statusEl = $row.find('.tsrb-inline-status-msg[data-booking-id="' + bookingId + '"]');
        var $btns     = $row.find('.tsrb-list-approve-cancel, .tsrb-list-reject-cancel');

        tsrbAdminProcessCancellation({
            bookingId: bookingId,
            actionType: 'reject',
            adminNote: '',
            $statusEl: $statusEl,
            $btns: $btns
        });
    });

    // List page: direct cancel from row.
    $(document).on('click', '.tsrb-list-direct-cancel', function (e) {
        e.preventDefault();

        var bookingId = $(this).data('booking-id');
        var $row      = $(this).closest('tr');
        var $statusEl = $row.find('.tsrb-inline-status-msg[data-booking-id="' + bookingId + '"]');
        var $btns     = $row.find('.tsrb-list-direct-cancel');

        tsrbAdminProcessCancellation({
            bookingId: bookingId,
            actionType: 'direct',
            adminNote: '',
            $statusEl: $statusEl,
            $btns: $btns
        });
    });

    /** ================= Inline booking status change ================= */
    $(document).on('click', '.tsrb-booking-status-save', function (e) {
        e.preventDefault();

        var bookingId = $(this).data('booking-id');
        var status    = $('.tsrb-booking-status-select[data-booking-id="' + bookingId + '"]').val();
        var $msg      = $('.tsrb-inline-status-msg[data-booking-id="' + bookingId + '"]');

        $msg.text('');

        $.ajax({
            url: TSRBAdmin.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'tsrb_update_booking_status',
                nonce: TSRBAdmin.nonce,
                booking_id: bookingId,
                status: status,
                admin_note: ''
            }
        }).done(function (response) {
            if (response.success) {
                $msg.text(response.data.message).css('color', 'green');
            } else {
                $msg.text(response.data && response.data.message ? response.data.message : 'Error').css('color', 'red');
            }
        }).fail(function () {
            $msg.text('Error').css('color', 'red');
        });
    });

    /** ================= Bookings list: bulk actions helpers ================= */

    $(document).on('change', '.tsrb-check-all', function () {
        var checked = $(this).is(':checked');
        $('.tsrb-booking-checkbox').prop('checked', checked);
    });

    $(document).on('change', '.tsrb-booking-checkbox', function () {
        var all     = $('.tsrb-booking-checkbox').length;
        var checked = $('.tsrb-booking-checkbox:checked').length;
        $('.tsrb-check-all').prop('checked', all > 0 && all === checked);
    });

    $('.tsrb-bookings-bulk-form').on('submit', function () {
        var action = $('#tsrb-bulk-action').val();
        var count  = $('.tsrb-booking-checkbox:checked').length;

        if (action === '-1' || count === 0) {
            return true;
        }

        return window.confirm(TSRBAdmin.bulkconfirm || 'Apply bulk action to selected bookings?');
    });

    /** ================= Bookings list: Quick View modal ================= */

    $(document).on('click', '.tsrb-booking-quick-view', function (e) {
        e.preventDefault();

        var bookingId = $(this).data('booking-id');
        var $modal    = $('#tsrb-booking-quick-view-modal');
        var $body     = $('#tsrb-booking-modal-body');

        if (!bookingId) {
            return;
        }

        $body.html('<p>' + (TSRBAdmin.loadingtext || 'Loading booking details...') + '</p>');
        $modal.show();

        $.ajax({
            url: TSRBAdmin.ajaxurl,
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'tsrb_get_booking_quick_view',
                nonce: TSRBAdmin.nonce,
                booking_id: bookingId
            }
        }).done(function (response) {
            if (response.success && response.data && response.data.html) {
                $body.html(response.data.html);
            } else {
                var msg = (response.data && response.data.message) ? response.data.message : 'Failed to load booking details.';
                $body.html('<p style="color:red;">' + msg + '</p>');
            }
        }).fail(function () {
            $body.html('<p style="color:red;">Error loading booking details.</p>');
        });
    });

    $(document).on('click', '.tsrb-booking-modal-close', function () {
        $('#tsrb-booking-quick-view-modal').hide();
    });

    $(document).on('click', '#tsrb-booking-quick-view-modal', function (e) {
        if ($(e.target).attr('id') === 'tsrb-booking-quick-view-modal') {
            $('#tsrb-booking-quick-view-modal').hide();
        }
    });

    /** ================= Settings: Payment QR media uploader ================= */
    var qrFrame;

    $('#tsrb-payment-qr-upload').on('click', function (e) {
        e.preventDefault();

        if (qrFrame) {
            qrFrame.open();
            return;
        }

        qrFrame = wp.media({
            title: 'Pilih gambar QR pembayaran',
            button: { text: 'Gunakan gambar ini' },
            multiple: false
        });

        qrFrame.on('select', function () {
            var attachment = qrFrame.state().get('selection').first().toJSON();
            $('#tsrb_payment_qr_image_id').val(attachment.id);
            $('#tsrb-payment-qr-preview').html(
                '<img src="' + attachment.url + '" style="max-width:150px;height:auto;" />'
            );
        });

        qrFrame.open();
    });

    $('#tsrb-payment-qr-remove').on('click', function (e) {
        e.preventDefault();
        $('#tsrb_payment_qr_image_id').val('');
        $('#tsrb-payment-qr-preview').empty();
    });

    /** ================= Settings: Blocked dates add/remove ================= */
    $('#tsrb-add-blocked-date').on('click', function (e) {
        e.preventDefault();

        var row = $(
            '<tr>' +
                '<td><input type="date" name="tsrb_settings[blocked_dates][]" style="width: 100%;" /></td>' +
                '<td><button type="button" class="button tsrb-remove-blocked-date">&times;</button></td>' +
            '</tr>'
        );

        $('#tsrb-blocked-dates-table tbody').append(row);
    });

    $(document).on('click', '.tsrb-remove-blocked-date', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    /** ================= Studios: Gallery media uploader ================= */
    var studioGalleryFrame;

    $('#tsrb-studio-gallery-add').on('click', function (e) {
        e.preventDefault();

        if (studioGalleryFrame) {
            studioGalleryFrame.open();
            return;
        }

        studioGalleryFrame = wp.media({
            title: 'Pilih gambar studio',
            button: { text: 'Gunakan gambar ini' },
            multiple: true
        });

        studioGalleryFrame.on('select', function () {
            var selection = studioGalleryFrame.state().get('selection');
            if (!selection || selection.length === 0) {
                return;
            }

            var ids  = [];
            var html = '';

            selection.each(function (attachment) {
                var att = attachment.toJSON();
                ids.push(att.id);

                var imgUrl = att.url;
                if (att.sizes && att.sizes.thumbnail) {
                    imgUrl = att.sizes.thumbnail.url;
                }

                html += '<div class="tsrb-studio-gallery-item" data-attachment-id="' + att.id + '">';
                html += '  <img src="' + imgUrl + '" alt="" />';
                html += '</div>';
            });

            var existingIds = $('#tsrb-studio-gallery-ids').val();
            if (existingIds) {
                existingIds.split(',').forEach(function (id) {
                    id = parseInt(id, 10);
                    if (id && ids.indexOf(id) === -1) {
                        ids.push(id);
                    }
                });
            }

            $('#tsrb-studio-gallery-ids').val(ids.join(','));
            $('#tsrb-studio-gallery-preview').html(html);
        });

        studioGalleryFrame.open();
    });

    $('#tsrb-studio-gallery-clear').on('click', function (e) {
        e.preventDefault();
        $('#tsrb-studio-gallery-ids').val('');
        $('#tsrb-studio-gallery-preview').empty();
    });

    /** ================= Bookings list: Payment Timer countdown ================= */

    // Ambil timestamp server saat render (dalam detik) dari atribut tabel.
    var $bookingsTable    = $('.tsrb-bookings-table');
    var serverNowAtRender = parseInt($bookingsTable.data('server-now'), 10) || 0;
    // Waktu browser (ms) ketika halaman selesai render.
    var clientNowAtRender = Date.now();

    function tsrbFormatCountdown(seconds) {
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

    function tsrbGetServerNowApprox() {
        if (!serverNowAtRender) {
            // fallback: pakai waktu browser langsung (dalam detik)
            return Math.floor(Date.now() / 1000);
        }
        var elapsedMs = Date.now() - clientNowAtRender;
        return serverNowAtRender + Math.floor(elapsedMs / 1000);
    }

    function tsrbUpdatePaymentTimers() {
        var serverNow = tsrbGetServerNowApprox();

        $('.js-tsrb-payment-timer').each(function () {
            var $badge  = $(this);
            var expires = parseInt($badge.data('expires'), 10);
            var $label  = $badge.find('.tsrb-timer-label');

            if (!expires || isNaN(expires)) {
                return;
            }

            var secondsLeft = expires - serverNow;

            if (secondsLeft <= 0) {
                $label.text('Expired');
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

            $label.text(tsrbFormatCountdown(secondsLeft));
        });
    }

    tsrbUpdatePaymentTimers();
    setInterval(tsrbUpdatePaymentTimers, 30000);
});
