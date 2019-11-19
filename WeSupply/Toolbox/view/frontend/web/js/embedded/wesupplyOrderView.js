define([
    'jquery',
    'Magento_Ui/js/modal/modal'
], function ($, modal) {
    'use strict';

    var options = {
        type: 'slide',
        responsive: true,
        innerScroll: false,
        modalClass: 'order-view-modal',
        buttons: [{
            text: $.mage.__('Close'),
            class: 'close-order-view',
            click: function () {
                this.closeModal();
            }
        }]
    };

    var createIframe = function (iframeUrl, platform) {
        return $('<iframe />', {
            id: 'order-iframe',
            class: 'embedded-iframe',
            src: iframeUrl + '&platformType=' + platform,
            width: '100%',
            allowfullscreen: true,
            frameborder: 0,
            allow: 'geolocation',
            scrolling: 'no'
        });
    };

    var calcMaxHeight = function(iframe)
    {
        window.scrollTo(0, 0);
        return window.innerHeight - iframe.parentElement.offsetTop;
    };

    return {
        init: function(platform)
        {
            var viewContainer = $('#order-view-container');
            var orderView = modal(options, viewContainer);

            $('.action.view.iframe-order').on('click', function()
            {
                viewContainer.trigger('processStart');
                viewContainer.html(createIframe($(this).data('url'), platform));

                $('#order-iframe').on('load', function(){

                    var resizeTo = 0,
                        headerHeight = $('header').outerHeight(),
                        windowHeight = $(window).innerHeight(),
                        availableHeight =  windowHeight - headerHeight - 70,
                        isOldIE = (navigator.userAgent.indexOf("MSIE") !== -1);

                    iFrameResize({
                        log: false,
                        minHeight: availableHeight,
                        resizeFrom: 'parent',
                        scrolling: true,
                        inPageLinks: true,
                        autoResize: true,
                        enablePublicMethods: true,
                        heightCalculationMethod: isOldIE ? 'max' : 'bodyScroll',
                        initCallback: function(iframe) {
                            iframe.style.height = availableHeight + 'px';
                        },
                        resizedCallback: function(messageData) {
                            setTimeout(function() {
                                if (resizeTo) {
                                    messageData.iframe.style.height = resizeTo + 'px';
                                    $('html, body').animate({ scrollTop: 0 }, 'fast');
                                }
                                messageData.iframe.style.visibility = 'visible';
                            }, 300);
                        },
                        messageCallback: function(messageData) {
                            if (messageData.message.event === 'openReturnPanel') {
                                resizeTo = calcMaxHeight(messageData.iframe);
                            }
                            if (messageData.message.event === 'closeReturnPanel') {
                                resizeTo = 0;
                            }
                        }
                    });

                    viewContainer
                        .trigger('processStop')
                        .height($('.order-view-modal').height());
                    orderView.openModal();
                });
            });
        }
    }
});