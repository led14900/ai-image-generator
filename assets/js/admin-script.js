jQuery(document).ready(function ($) {
    'use strict';

    // Inject CSS to fix metabox height issues - ROBUST FIX
    $('<style>')
        .prop('type', 'text/css')
        .html(
            '#aiig-meta-box { height: auto !important; overflow: visible !important; display: block !important; } ' +
            '#aiig-meta-box .inside { height: auto !important; overflow: visible !important; max-height: none !important; min-height: fit-content !important; padding-bottom: 20px !important; display: block !important; } ' +
            '#aiig-meta-box .inside::after { content: ""; display: table; clear: both; } ' +
            '.aiig-progress { height: auto !important; overflow: visible !important; box-sizing: border-box; }'
        )
        .appendTo('head');

    function createMessage(className, message) {
        return $('<div>').addClass(className).text(message || '');
    }

    function addNotice(type, message) {
        var $notice = $('<div>').addClass('notice is-dismissible').addClass(type);
        $notice.append($('<p>').text(message || ''));
        $('.aiig-settings-wrap h1').after($notice);
        return $notice;
    }

    function setMessage($target, className, message) {
        $target.empty().append(createMessage(className, message));
    }

    function getSafeUrl(url) {
        try {
            var parsed = new URL(url, window.location.href);
            return (parsed.protocol === 'http:' || parsed.protocol === 'https:') ? parsed.href : '';
        } catch (e) {
            return '';
        }
    }

    // ==================== SETTINGS PAGE ====================

    // Track unsaved changes to API keys
    var hasUnsavedPromptKey = false;
    var hasUnsavedGeminiKey = false;

    // Auto-fill model based on provider
    $('#aiig-prompt-provider').on('change', function () {
        var provider = $(this).val();
        var $modelInput = $('input[name="aiig_settings[prompt_model]"]');

        if (provider === 'openai') {
            $modelInput.val('gpt-5-nano-2025-08-07');
        } else if (provider === 'gemini') {
            $modelInput.val('gemini-2.5-flash-lite');
        }
    });

    // Track changes to Prompt API key
    $('input[name="aiig_settings[prompt_api_key]"]').on('input', function () {
        hasUnsavedPromptKey = $(this).val().trim() !== '';
    });

    // Track changes to Agent Platform service account JSON
    $('textarea[name="aiig_settings[vertex_service_account_json]"]').on('input', function () {
        hasUnsavedGeminiKey = $(this).val().trim() !== '';
    });

    $('form[action="options.php"]').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $submitButton = $form.find('input[type="submit"]');
        var originalText = $submitButton.val();

        $submitButton.prop('disabled', true).val('Đang lưu...');

        var formData = new FormData($form[0]);
        formData.set('action', 'aiig_save_settings');
        formData.set('nonce', aiigData.nonce);

        $.ajax({
            url: aiigData.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    var $message = addNotice('notice-success', response.data.message);
                    setTimeout(function () {
                        $message.fadeOut(function () {
                            $(this).remove();
                        });
                    }, 3000);

                    // Reset unsaved flags after successful save
                    hasUnsavedPromptKey = false;
                    hasUnsavedGeminiKey = false;
                } else {
                    addNotice('notice-error', response.data.message || 'Lỗi');
                }
            },
            error: function () {
                addNotice('notice-error', 'Lỗi kết nối');
            },
            complete: function () {
                $submitButton.prop('disabled', false).val(originalText);
            }
        });
    });

    $('.aiig-nav-tab').on('click', function (e) {
        e.preventDefault();
        var tabId = $(this).data('tab');
        $('.aiig-nav-tab').removeClass('active');
        $(this).addClass('active');
        $('.aiig-tab-content').removeClass('active');
        $('#' + tabId).addClass('active');
    });

    function saveSettingsBeforeTest(callback) {
        var $form = $('form[action="options.php"]');
        if (!$form.length) {
            callback(false);
            return;
        }

        var formData = new FormData($form[0]);
        formData.set('action', 'aiig_save_settings');
        formData.set('nonce', aiigData.nonce);

        $.ajax({
            url: aiigData.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    // Reset unsaved flags after successful save
                    hasUnsavedPromptKey = false;
                    hasUnsavedGeminiKey = false;
                    callback(true);
                } else {
                    callback(false);
                }
            },
            error: function () {
                callback(false);
            }
        });
    }

    function testConnection(action, $button, $result) {
        var $form = $button.closest('form');
        if (!$form.length) {
            setMessage($result, 'aiig-message error', 'Form not found');
            return;
        }

        var originalHtml = $button.html();
        $button.prop('disabled', true).text('Đang kiểm tra...');


        var formData = new FormData($form[0]);
        formData.set('action', action);
        formData.set('nonce', aiigData.nonce);

        $.ajax({
            url: aiigData.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    setMessage($result, 'aiig-message success', response.data.message);
                } else {
                    setMessage($result, 'aiig-message error', response.data.message || 'Failed');
                }
            },
            error: function () {
                setMessage($result, 'aiig-message error', 'Connection failed');
            },
            complete: function () {
                $button.prop('disabled', false).html(originalHtml);
            }
        });
    }

    $(document).on('click', '.aiig-test-prompt-connection', function (e) {
        e.preventDefault();
        var $button = $(this);
        var $result = $('#aiig-test-prompt-result');

        // Always save settings before testing
        $result.html(
            '<div class="aiig-message" style="background: #e7f3ff; border-left-color: #2271b1; color: #2271b1;">' +
            '💾 Đang lưu cài đặt và kết nối tới AI Provider...' +
            '</div>'
        );

        // Trigger save first, then test
        saveSettingsBeforeTest(function (success) {
            if (success) {
                hasUnsavedPromptKey = false;

                // Show testing message
                $result.html(
                    '<div class="aiig-message" style="background: #fff3e0; border-left-color: #ff9800; color: #e65100;">' +
                    '🔌 Đang kết nối tới AI Provider...' +
                    '</div>'
                );

                testConnection('aiig_test_connection', $button, $result);
            } else {
                $result.html(
                    '<div class="aiig-message error">' +
                    '❌ Không thể lưu cài đặt. Vui lòng thử lại.' +
                    '</div>'
                );
            }
        });
    });

    $(document).on('click', '.aiig-test-gemini-connection', function (e) {
        e.preventDefault();
        var $button = $(this);
        var $result = $('#aiig-test-gemini-result');

        // Always save settings before testing
        $result.html(
            '<div class="aiig-message" style="background: #e7f3ff; border-left-color: #2271b1; color: #2271b1;">' +
            '💾 Đang lưu cài đặt và kết nối tới AI Provider...' +
            '</div>'
        );

        // Trigger save first, then test
        saveSettingsBeforeTest(function (success) {
            if (success) {
                hasUnsavedGeminiKey = false;

                // Show testing message
                $result.html(
                    '<div class="aiig-message" style="background: #fff3e0; border-left-color: #ff9800; color: #e65100;">' +
                    '🔌 Đang kết nối tới AI Provider...' +
                    '</div>'
                );

                testConnection('aiig_test_image_generation', $button, $result);
            } else {
                $result.html(
                    '<div class="aiig-message error">' +
                    '❌ Không thể lưu cài đặt. Vui lòng thử lại.' +
                    '</div>'
                );
            }
        });
    });

    // ==================== META BOX ====================

    $('#aiig-generate-prompts').on('click', function (e) {
        e.preventDefault();
        var $button = $(this);
        var $promptList = $('#aiig-prompt-list');
        var promptCount = $('#aiig-prompt-count').val();

        $button.prop('disabled', true).html('⏳ Đang tạo...');
        $promptList.html('');

        $.ajax({
            url: aiigData.ajax_url,
            type: 'POST',
            data: {
                action: 'aiig_generate_prompts',
                nonce: aiigData.nonce,
                post_id: $('#post_ID').val(),
                count: promptCount
            },
            success: function (response) {
                if (response.success && response.data.prompts) {
                    $promptList.empty();
                    response.data.prompts.forEach(function (prompt, index) {
                        var $item = $('<div>').addClass('aiig-prompt-item').css('margin-bottom', '15px');
                        var $label = $('<label>')
                            .css({ fontWeight: 'bold', display: 'block', marginBottom: '5px' })
                            .text('Prompt ' + (index + 1) + ':');
                        var $textarea = $('<textarea>')
                            .addClass('aiig-prompt-text widefat')
                            .attr({ rows: 3, 'data-index': index })
                            .css('width', '100%')
                            .val(prompt);

                        $item.append($label, $textarea);
                        $promptList.append($item);
                    });
                    $('#aiig-generate-images').prop('disabled', false);
                } else {
                    alert(response.data.message || 'Lỗi khi tạo prompts');
                }
            },
            error: function () {
                alert('Lỗi kết nối');
            },
            complete: function () {
                $button.prop('disabled', false).html('🎨 Tạo Prompts');
            }
        });
    });

    $('#aiig-generate-images').on('click', function (e) {
        e.preventDefault();
        var $button = $(this);
        var $gallery = $('#aiig-image-gallery');
        var prompts = [];

        $('.aiig-prompt-text').each(function () {
            prompts.push($(this).val());
        });

        if (prompts.length === 0) {
            alert('Vui lòng tạo prompts trước!');
            return;
        }

        $button.prop('disabled', true).html('⏳ Đang tạo ảnh...');

        $gallery.html('<div class="aiig-progress" style="background: #f0f6fc; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa; margin-top: 15px;">' +
            '<h3 style="margin-top: 0; font-size: 13px;">📊 Tiến trình & Kết quả:</h3>' +
            '<div id="aiig-progress-messages" style="font-size: 12px;"></div>' +
            '</div>');

        var currentIndex = 0;
        var totalPrompts = prompts.length;
        var successCount = 0;

        function generateNextImage() {
            if (currentIndex >= totalPrompts) {
                $button.prop('disabled', false).html('🚀 Tạo Ảnh');
                $('#aiig-progress-messages').append(
                    '<p style="color: green; font-weight: bold; margin-top: 10px; padding: 8px; background: #d4edda; border-radius: 4px;">' +
                    '✅ Hoàn thành! ' + successCount + '/' + totalPrompts + ' ảnh đã được thêm vào Thư viện Media' +
                    '</p>'
                );
                return;
            }

            var promptNumber = currentIndex + 1;
            var $progressItem = $('<div id="progress-' + promptNumber + '" style="padding: 10px; margin: 10px 0; background: white; border-radius: 4px; border: 1px solid #ddd;">' +
                '<span style="color: #0073aa;">⏳</span> Đang tạo ảnh ' + promptNumber + '/' + totalPrompts + '...' +
                '</div>');
            $('#aiig-progress-messages').append($progressItem);

            var request_id = 'req_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

            var pollCount = 0;
            var maxPolls = 3; // 3 retries as requested

            function pollForImage() {
                pollCount++;
                if (pollCount > maxPolls) {
                    handleError('Đã kiểm tra 3 lần nhưng chưa thấy ảnh. Vui lòng thử lại sau.');
                    return;
                }

                $.ajax({
                    url: aiigData.ajax_url,
                    type: 'POST',
                    timeout: 180000, // 3 minutes timeout for the request
                    data: {
                        action: 'aiig_generate_images',
                        nonce: aiigData.nonce,
                        post_id: $('#post_ID').val(),
                        prompt: prompts[currentIndex],
                        request_id: request_id
                    },
                    success: function (response) {
                        if (response.success && response.data.image) {
                            successCount++;
                            var safeImageUrl = getSafeUrl(response.data.image.url);
                            var $imageProgress = $('#progress-' + promptNumber).empty();
                            $imageProgress.append(
                                $('<div>')
                                    .css({ marginBottom: '5px', fontWeight: 'bold', color: 'green' })
                                    .text('Ảnh ' + promptNumber + ': Đã upload vào Thư viện')
                            );

                            if (safeImageUrl) {
                                $imageProgress.append(
                                    $('<a>')
                                        .attr({ href: safeImageUrl, target: '_blank', rel: 'noopener noreferrer' })
                                        .append(
                                            $('<img>')
                                                .attr({ src: safeImageUrl, alt: '' })
                                                .css({
                                                    width: '100%',
                                                    height: 'auto',
                                                    borderRadius: '4px',
                                                    border: '1px solid #eee',
                                                    display: 'block'
                                                })
                                        )
                                );
                            }
                            currentIndex++;
                            generateNextImage();
                        } else {
                            // Check if it's a processing message or a real error
                            var msg = response.data.message || '';
                            // If server says "Processing", we wait and retry
                            if (msg.includes('Đang xử lý') || msg.includes('Processing')) {
                                updateStatus('⏳ Đang xử lý... (Lần ' + pollCount + '/' + maxPolls + ')');
                                setTimeout(pollForImage, 5000);
                            } else {
                                // For other errors, we might want to fail, OR if it's a vague error, maybe retry?
                                // But usually explicit false success means logic error.
                                handleError(msg || 'Không xác định');
                            }
                        }
                    },
                    error: function (xhr, status, error) {
                        // Connection error, timeout, etc.
                        // Wait 5s and check again as requested
                        updateStatus('⚠️ Lỗi kết nối. Đang kiểm tra lại (Lần ' + pollCount + '/' + maxPolls + ')...');
                        setTimeout(pollForImage, 5000);
                    }
                });
            }

            function updateStatus(msg) {
                var $item = $('#progress-' + promptNumber);
                // Try to find existing status text or append if structure is simple
                // We'll just update the text content of the div but keep the ID
                // To be safe and keep it simple, let's just update the content
                // But we want to keep the "Image X/Y" part if possible? 
                // The original code was: '<span style="color: #0073aa;">⏳</span> Đang tạo ảnh ' + promptNumber + '/' + totalPrompts + '...'

                // Let's just overwrite with the new status message to be clear
                $item.empty().append($('<span>').css('color', 'orange').text('🔄 '), document.createTextNode(msg));
            }

            function handleError(message) {
                $('#progress-' + promptNumber)
                    .empty()
                    .append(
                        $('<div>')
                            .css({ color: '#d63638', fontWeight: 'bold', marginBottom: '5px' })
                            .text('Ảnh ' + promptNumber + ': Thất bại'),
                        $('<div>')
                            .css({
                                color: '#666',
                                fontSize: '12px',
                                background: '#fff5f5',
                                padding: '8px',
                                borderLeft: '3px solid #d63638'
                            })
                            .text('Lỗi: ' + message)
                    );
                currentIndex++;
                generateNextImage();
            }

            pollForImage();
        }

        generateNextImage();
    });

    // ==================== IMAGE PROVIDER TOGGLE ====================

    // Show/hide provider-specific config sections on radio change.
    $( 'input[name="aiig_settings[image_provider]"]' ).on( 'change', function () {
        var val = $( this ).val();
        $( '#aiig-section-gemini' ).toggle( val === 'gemini' );
        $( '#aiig-section-9router' ).toggle( val === '9router' );
    } );

    // ==================== TEST 9ROUTER CONNECTION ====================

    $(document).on('click', '.aiig-test-9router-connection', function (e) {
        e.preventDefault();
        var $button = $(this);
        var $result = $('#aiig-test-9router-result');

        $result.empty().append(
            $('<div>')
                .addClass('aiig-message')
                .css({ background: '#e7f3ff', borderLeftColor: '#2271b1', color: '#2271b1' })
                .text('Đang lưu cài đặt và kết nối tới 9router...')
        );

        saveSettingsBeforeTest(function (success) {
            if (success) {
                testConnection('aiig_test_image_generation', $button, $result);
            } else {
                setMessage($result, 'aiig-message error', 'Không thể lưu cài đặt. Vui lòng thử lại.');
            }
        });
    });
});
