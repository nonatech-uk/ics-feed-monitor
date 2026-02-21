(function($) {
    'use strict';

    // ─── Copy to Clipboard ──────────────────────────────
    $(document).on('click', '.icsfm-copy-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var url = btn.data('url');

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function() {
                showCopied(btn);
            }).catch(function() {
                fallbackCopy(url, btn);
            });
        } else {
            fallbackCopy(url, btn);
        }
    });

    function fallbackCopy(text, btn) {
        var temp = $('<input>');
        $('body').append(temp);
        temp.val(text).select();
        try {
            document.execCommand('copy');
            showCopied(btn);
        } catch (e) {
            alert('Copy failed. URL: ' + text);
        }
        temp.remove();
    }

    function showCopied(btn) {
        var original = btn.text();
        btn.text('Copied!').addClass('copied');
        setTimeout(function() {
            btn.text(original).removeClass('copied');
        }, 2000);
    }

    // ─── Test Feed Source URL (supports multiple per page) ─
    $(document).on('click', '.icsfm-test-feed-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var urlField = btn.data('url-field');
        var url;
        var resultEl;

        if (urlField) {
            // Pair form: data-url-field points to the input ID
            url = $('#' + urlField).val();
            // Result element is the sibling span
            resultEl = btn.siblings('.icsfm-test-result');
            if (resultEl.length === 0) {
                // Try finding by ID convention
                var direction = urlField.replace('source_url_', '');
                resultEl = $('#icsfm-test-result-' + direction);
            }
        } else {
            // Legacy: single test button
            url = $('#source_url').val();
            resultEl = $('#icsfm-test-result');
        }

        if (!url) {
            resultEl.text('Please enter a URL first.').attr('class', 'icsfm-test-result error');
            return;
        }

        btn.prop('disabled', true);
        resultEl.text('Testing...').attr('class', 'icsfm-test-result loading');

        $.post(icsfm.ajax_url, {
            action: 'icsfm_test_feed',
            nonce: icsfm.nonce,
            url: url
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                resultEl.text(response.data.message).attr('class', 'icsfm-test-result success');
            } else {
                var msg = response.data.message || response.data || 'Test failed.';
                resultEl.text(msg).attr('class', 'icsfm-test-result error');
            }
        }).fail(function() {
            btn.prop('disabled', false);
            resultEl.text('Request failed.').attr('class', 'icsfm-test-result error');
        });
    });

    // ─── Platform B dropdown filtering ───────────────────
    // When Platform A is selected, hide that option from Platform B
    $(document).on('change', '#platform_a_id', function() {
        var selectedA = $(this).val();
        var platformB = $('#platform_b_id');

        if (!platformB.length) return;

        // Show all options first
        platformB.find('option').show();

        // Hide the selected Platform A option from Platform B
        if (selectedA) {
            platformB.find('option[value="' + selectedA + '"]').hide();
            // If Platform B currently has the same value, reset it
            if (platformB.val() === selectedA) {
                platformB.val('');
            }
        }
    });

    // Also update direction labels dynamically in create mode
    function updateDirectionLabels() {
        var platA = $('#platform_a_id');
        var platB = $('#platform_b_id');

        if (!platA.length || !platB.length) return;

        var nameA = platA.find('option:selected').text().trim();
        var nameB = platB.find('option:selected').text().trim();

        if (nameA && nameA !== '— Select Platform A —' && nameB && nameB !== '— Select Platform B —') {
            $('#icsfm-label-a-to-b').text(nameA + ' → ' + nameB);
            $('#icsfm-label-b-to-a').text(nameB + ' → ' + nameA);
            $('.icsfm-platform-a-name').text(nameA);
            $('.icsfm-platform-b-name').text(nameB);
        }
    }

    $(document).on('change', '#platform_a_id, #platform_b_id', updateDirectionLabels);

    // Trigger on page load to set initial state
    $(function() {
        if ($('#platform_a_id').length) {
            $('#platform_a_id').trigger('change');
        }
    });

    // ─── Test Webhook ───────────────────────────────────
    $(document).on('click', '#icsfm-test-webhook', function(e) {
        e.preventDefault();
        var btn = $(this);
        var resultEl = $('#icsfm-webhook-test-result');

        btn.prop('disabled', true);
        resultEl.text('Sending...').attr('class', 'icsfm-test-result loading');

        $.post(icsfm.ajax_url, {
            action: 'icsfm_test_webhook',
            nonce: icsfm.nonce
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                resultEl.text(response.data.message).attr('class', 'icsfm-test-result success');
            } else {
                var msg = response.data.message || response.data || 'Webhook test failed.';
                resultEl.text(msg).attr('class', 'icsfm-test-result error');
            }
        }).fail(function() {
            btn.prop('disabled', false);
            resultEl.text('Request failed.').attr('class', 'icsfm-test-result error');
        });
    });

    // ─── Test Email ─────────────────────────────────────
    $(document).on('click', '#icsfm-test-email', function(e) {
        e.preventDefault();
        var btn = $(this);
        var resultEl = $('#icsfm-email-test-result');

        btn.prop('disabled', true);
        resultEl.text('Sending...').attr('class', 'icsfm-test-result loading');

        $.post(icsfm.ajax_url, {
            action: 'icsfm_test_email',
            nonce: icsfm.nonce
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                resultEl.text(response.data.message).attr('class', 'icsfm-test-result success');
            } else {
                var msg = response.data.message || response.data || 'Email test failed.';
                resultEl.text(msg).attr('class', 'icsfm-test-result error');
            }
        }).fail(function() {
            btn.prop('disabled', false);
            resultEl.text('Request failed.').attr('class', 'icsfm-test-result error');
        });
    });

    // ─── Test Healthcheck ────────────────────────────────
    $(document).on('click', '#icsfm-test-healthcheck', function(e) {
        e.preventDefault();
        var btn = $(this);
        var resultEl = $('#icsfm-healthcheck-test-result');

        btn.prop('disabled', true);
        resultEl.text('Pinging...').attr('class', 'icsfm-test-result loading');

        $.ajax({
            url: icsfm.ajax_url,
            type: 'POST',
            data: {
                action: 'icsfm_test_healthcheck',
                nonce: icsfm.nonce
            },
            success: function(response) {
                btn.prop('disabled', false);
                if (response.success) {
                    resultEl.text(response.data.message).attr('class', 'icsfm-test-result success');
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : (response.data || 'Healthcheck test failed.');
                    resultEl.text(msg).attr('class', 'icsfm-test-result error');
                }
            },
            error: function(xhr, status, error) {
                btn.prop('disabled', false);
                var detail = 'AJAX error: ' + status + ' — ' + (error || 'unknown') + ' (HTTP ' + xhr.status + ')';
                if (xhr.responseText) {
                    detail += ' Response: ' + xhr.responseText.substring(0, 200);
                }
                resultEl.text(detail).attr('class', 'icsfm-test-result error');
            }
        });
    });

})(jQuery);
