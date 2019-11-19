define([
    'jquery'
], function ($) {
    'use strict';

    var createIframe = function (iframeUrl) {
        return $('<iframe />', {
            id: 'ordersview-iframe',
            class: 'embedded-iframe',
            src: iframeUrl,
            width: '100%',
            allowfullscreen: true,
            frameborder: 0,
            allow: 'geolocation',
            scrolling: 'no'
        });
    };

    var calcMaxHeight = function(iframe)
    {
        return window.innerHeight - iframe.parentElement.offsetTop;
    };

    return {
        load: function(iframeUrl)
        {
            var viewContainer = $('#ordersview-container');
            var loadingContainer = $('.loading-container');

            viewContainer.html(createIframe(iframeUrl));
            $('#ordersview-iframe').on('load', function(){
                $(this).css('visibility', 'visible');
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
                            // messageData.iframe.style.visibility = 'visible';
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

                loadingContainer.hide();
                viewContainer.show();
            });
        }
    }
});