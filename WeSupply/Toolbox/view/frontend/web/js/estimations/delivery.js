define([
    'jquery',
    'underscore',
    'mage/translate'
], function ($, _, __) {
    "use strict";

    return {
        initProduct: function(ajaxUrl)
        {
            this.data = {
                isKeypress: false,
                estimationUrl: ajaxUrl,
                productId: $("input[type='hidden'][name='product_id']"),
                productType: $("input[type='hidden'][name='product_type']"),
                postCode: $("input[type='hidden'][name='post_code']"),
                countryCode: $("input[type='hidden'][name='country_code']"),
                allowedCountries: $("input[type='hidden'][name='has_allowed_countries']"),
                orderWithin: $("input[type='hidden'][name='order_within']"),
                displaySpinner: $("input[type='hidden'][name='display_spinner']"),
                containers: {
                    estimationWrapper: $('.estimation-wrapper'),
                    headingContainer: $('.heading-container'),
                    responseContainer: $('.response-container'),
                    loader: $('.estimation-wrapper #loader'),
                    date: $('.estimation-wrapper #date'),
                    prePostCode: $('.estimation-wrapper #pre_post_code'),
                    postCode: $('.estimation-wrapper #post_code'),
                    countryCode: $('.estimation-wrapper #country_code'),
                    orderWithin: $('.estimation-wrapper #order_within'),
                    orderWithinContainer: $('.order-within-container'),
                    error: $('.estimation-wrapper #error')
                },
                inputs: {
                    postCodeInput: $("input[type='text'][name='visible_post_code']")
                }
            };

            if (this.data.displaySpinner.val() == 1) {
                this.data.containers.estimationWrapper.addClass('show-spinner');
            }

            let that = this;
            if (this.data.productType.val() === 'configurable') {
                this.waitUntil(function() {
                    return $('.swatch-attribute').length;
                }, function() {
                    that.data.options = $('.swatch-attribute');
                    that.data.optionsCount = $('.swatch-attribute').length;
                    that.data.options.on('change', function() {
                        that.observeOptionSelect();
                    });
                }, function() {
                    // do nothing
                });
            } else {
                this.estimateDelivery();
            }

            this.data.containers.postCode.add(this.data.containers.error).on('click', function() {
                that.updateNewLocation();
            });
            this.data.inputs.postCodeInput.on('blur', function() {
                if (that.data.isKeypress === false) {
                    that.estimateNewLocation();
                }
                that.data.isKeypress = false;
            });
            this.data.inputs.postCodeInput.on('keypress', function(e) {
                if (e.which === 13) {
                    that.data.isKeypress = true;
                    that.estimateNewLocation();
                }
            });

            return this;
        },
        waitUntil: function (isReady, success, error, count, interval){
            if (count === undefined) {
                count = 300;
            }
            if (interval === undefined) {
                interval = 20;
            }
            if (isReady()) {
                success();
                return;
            }
            let that = this;
            setTimeout(function(){
                if (!count) {
                    if (error !== undefined) {
                        error();
                    }
                } else {
                    that.waitUntil(isReady, success, error, count -1, interval);
                }
            }, interval);
        },
        observeOptionSelect: function()
        {
            let that = this,
                selectedCount = 0;

            this.data.configurable = {};
            this.data.configurableRaw = {};
            this.data.options.each(function(i, option) {
                if (option.hasAttribute('option-selected')) {
                    selectedCount++;
                    that.data.configurableRaw[$(this).attr('attribute-id')] = $(this).attr('option-selected');
                }
            });

            if (selectedCount === this.data.optionsCount) {
                this.estimateDelivery();
            }
        },
        estimateDelivery: function()
        {
            let that = this;
            $.ajax({
                method: "POST",
                global: false,
                cache: false,
                url: that.data.estimationUrl,
                data: {
                    allowed_countries: that.data.allowedCountries.val(),
                    product_id: that.data.productId.val(),
                    postcode: that.data.postCode.val(),
                    country_code: that.data.countryCode.val(),
                    selected_product: that.getSimpleProductId()
                },
                dataType: "json",
                beforeSend: function() {
                    // that.showContainers([that.data.containers.loader]);
                    if (that.data.displaySpinner.val() == 1 || that.data.containers.responseContainer.hasClass('visible')) {
                        that.data.containers.loader.addClass('visible');
                    }
                }
            }).done(function(response){
                that.hideContainers([
                    // that.data.containers.loader,
                    that.data.containers.error,
                    that.data.inputs.postCodeInput
                ]);
                // that.showContainers([
                //     that.data.containers.responseContainer
                // ]);
                that.data.containers.loader.removeClass('visible');
                that.data.containers.responseContainer.addClass('visible');
                that.data.containers.headingContainer.addClass('visible');
                if (response.success) {
                    that.setSuccessResponse(response);
                    that.showContainers([
                        that.data.containers.date,
                        that.data.containers.postCode,
                        that.data.containers.prePostCode,
                        that.data.containers.countryCode
                    ]);
                    if (response.estimate.time_remaining_seconds > 0 && response.estimate.time_remaining_seconds <= that.data.orderWithin.val()) {
                        that.showContainers([
                            that.data.containers.orderWithinContainer
                        ]);
                    }
                } else {
                    if (response.estimate.error !== '') {
                        that.hideContainers([
                            that.data.containers.date,
                            that.data.containers.postCode,
                            that.data.containers.prePostCode,
                            that.data.containers.countryCode,
                            that.data.containers.orderWithinContainer
                        ]);
                        that.showContainers([that.data.containers.error]);
                        that.setErrorResponse(response);
                    } else {
                        // that.hideContainers([
                        //     that.data.containers.responseContainer
                        // ]);
                        that.data.containers.responseContainer.removeClass('visible');
                        that.data.containers.headingContainer.removeClass('visible');
                    }
                }
            });

            return this;
        },
        getSimpleProductId: function()
        {
            let that = this, selectedProductId;
            if (this.data.productType.val() === 'configurable') {
                let simpleProducts = jQuery('[data-role=swatch-options]').data('mageSwatchRenderer').options.jsonConfig.index;
                $.each(simpleProducts, function(productId, options) {
                    if (_.isEqual(options, that.data.configurableRaw)) {
                        selectedProductId = productId;
                    }
                });
            }

            return selectedProductId;
        },
        setSuccessResponse: function(response)
        {
            // update text containers
            this.data.containers.date.text(response.estimate.estimated_delivery_date);
            this.data.containers.postCode.text(response.estimate.location_id);
            this.data.containers.countryCode.text(response.estimate.location_country);
            if (response.estimate.time_remaining_seconds > 0 && response.estimate.time_remaining_seconds <= this.data.orderWithin.val()) {
                this.data.containers.orderWithin.text(this.secondsToHMS(response.estimate.time_remaining_seconds));
            }
            // update inputs
            this.data.postCode.val(response.estimate.location_id);
            this.data.countryCode.val(response.estimate.location_country);
            this.data.inputs.postCodeInput.val(response.estimate.location_id);
        },
        setErrorResponse: function(response)
        {
            this.data.containers.error.text(response.estimate.error);
        },
        updateNewLocation: function()
        {
            this.hideContainers([
                this.data.containers.postCode,
                this.data.containers.error
            ]);
            this.showContainers([
                this.data.inputs.postCodeInput,
                this.data.containers.countryCode
            ]);
            this.data.inputs.postCodeInput.select();
        },
        estimateNewLocation: function()
        {
            this.data.inputs.postCodeInput.removeClass('empty');
            if (
                this.data.inputs.postCodeInput.val() !== '' &&
                this.data.inputs.postCodeInput.val() !== null
            ) {
                this.data.postCode.val(this.data.inputs.postCodeInput.val());
                this.estimateDelivery();
            } else {
                this.data.inputs.postCodeInput.addClass('empty');
            }
        },
        showContainers: function(elements)
        {
            elements.forEach(function(item) {
                item.show();
            });
        },
        hideContainers: function(elements)
        {
            elements.forEach(function(item) {
                item.hide();
            });
        },
        secondsToHMS: function(seconds)
        {
            let sec = parseInt(seconds, 10);

            let days = Math.floor(sec / (3600 * 24));
            sec  -= days * 3600 * 24;
            let hrs   = Math.floor(sec / 3600);
            sec  -= hrs * 3600;
            let mnts = Math.floor(sec / 60);
            sec  -= mnts * 60;

            let orderWithin  = days ? days > 1 ? days + ' day(s) ' : days + ' day ' : '';
                orderWithin += hrs  ? hrs > 1 ?  hrs + ' hrs ' : hrs + ' hr ' : '';
                orderWithin += mnts ? mnts > 1 ? mnts + ' mins ' :  mnts + ' min ' : '';

            return orderWithin;
        }
    };
});