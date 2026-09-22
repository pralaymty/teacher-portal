    <script>
        $('#markAttendanceBtn').on('click', function () {
            const btn = $(this);
            if (btn.is(':disabled')) return;
            const isLogoff = btn.data('action') === 'logoff';

            // show loader
            btn.prop('disabled', true);
            btn.find('.spinner-border').removeClass('d-none');
            const originalText = btn.find('.btn-text').text();
            btn.find('.btn-text').text(isLogoff ? 'Logging off...' : 'Marking attendance...');

            if (!navigator.geolocation) {
                alert('Geolocation is not supported by this browser.');
                btn.find('.spinner-border').addClass('d-none');
                btn.prop('disabled', false);
                btn.find('.btn-text').text(originalText);
                return;
            }

            navigator.geolocation.getCurrentPosition(function (position) {
                $.ajax({
                    url: isLogoff ? 'attendance-logoff.php' : 'attendance-mark.php',
                    type: 'POST',
                    data: {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        csrf_token: '<?= e($_SESSION['csrf_token'] ?? '') ?>'
                    },
                    dataType: 'json'
                }).done(function (response) {
                    if (response && response.success) {
                        alert(response.message);
                        window.location.reload();
                    } else {
                        alert((response && response.message) ? response.message : 'Unable to mark attendance.');
                        btn.find('.spinner-border').addClass('d-none');
                        btn.prop('disabled', false);
                        btn.find('.btn-text').text(originalText);
                    }
                }).fail(function (jqXHR) {
                    let msg = 'Unable to mark attendance right now. Please try again.';
                    try {
                        const parsed = jqXHR.responseJSON || JSON.parse(jqXHR.responseText || '{}');
                        if (parsed && parsed.message) msg = parsed.message;
                    } catch (e) {
                        // ignore parse errors
                    }
                    alert(msg);
                    btn.find('.spinner-border').addClass('d-none');
                    btn.prop('disabled', false);
                    btn.find('.btn-text').text(originalText);
                });

            }, function (error) {
                let message = 'Location access is required to mark attendance.';
                if (error && error.code === 1) message = 'Location permission denied. Please allow location access to mark attendance.';
                if (error && error.code === 2) message = 'Location information is unavailable at the moment.';
                if (error && error.code === 3) message = 'Location request timed out. Please try again.';
                alert(message);
                btn.find('.spinner-border').addClass('d-none');
                btn.prop('disabled', false);
                btn.find('.btn-text').text(originalText);
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
        });
    </script>
