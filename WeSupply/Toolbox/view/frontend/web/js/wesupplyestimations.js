define([
    'jquery',
    'mage/translate'
], function ($) {
    "use strict";

    var wesupplyestimations = wesupplyestimations || {};

    wesupplyestimations.url = '';
    wesupplyestimations.storeId = '';
    wesupplyestimations.postcode = '';
    wesupplyestimations.countrycode = '';
    wesupplyestimations.price = 0;
    wesupplyestimations.currency = '';

    wesupplyestimations.makeAlert = false;
    wesupplyestimations.testVariable = 'Test Variable';

    wesupplyestimations.responseDiv = $('#estimated_response');
    wesupplyestimations.estimationsDiv = $('#estimations_div');
    wesupplyestimations.dateElement =  $('#date');
    wesupplyestimations.preZipText = $('#pre_zip');
    wesupplyestimations.zipElement =  $('#zip');
    wesupplyestimations.zipInput = $("#input_zip");
    wesupplyestimations.countryElement =  $('#country');
    wesupplyestimations.errorDiv = $('#error');
    wesupplyestimations.errorText = $('#error-message');
    wesupplyestimations.loaderDiv = $('.loader');

    wesupplyestimations.initialize = function (url, storeId, postcode, countrycode, price, currency, estimationsDone)
    {
        var that = this;

        that.url = url;
        that.storeId = storeId;
        that.postcode = postcode;
        that.countrycode = countrycode;
        that.price = price;
        that.currency = currency;


        wesupplyestimations.zipElement.on("click", function() {
            var $this = $(this);
            $this.hide().siblings("#input_zip").val($this.text()).show();
        });

        wesupplyestimations.zipInput.on("blur", function() {
            var $this = $(this);
            $this.hide().siblings("#zip").text($this.val()).show();
            that.postcode = that.zipInput.val();

            that.checkEstimations();

        }).hide();

        wesupplyestimations.zipInput.keypress(function(e){
            if(e.which == '13'){
                var $this = $(this);
                $this.hide().siblings("#zip").text($this.val()).show();
                that.postcode = that.zipInput.val();

                that.checkEstimations();
            }
        }).hide();

        wesupplyestimations.responseDiv.on("blur", function() {
            wesupplyestimations.zipInput.hide().siblings("#zip").text(wesupplyestimations.zipInput.val()).show();



        })


        if(estimationsDone === ''){
            that.checkEstimations();
        }

    };


    wesupplyestimations.checkEstimations = function(){

        var that = this;

        that.loaderDiv.show();
        that.responseDiv.hide();

        $.ajax({
            method: "POST",
            global: false,
            cache: false,
            url: that.url,
            data: {storeId : that.storeId, postcode:that.postcode, countrycode: that.countrycode, price: that.price, currency: that.currency},
            dataType: "json",
            // showLoader: true,
            // loaderContext: that.loaderDiv
        })
            .done(function(response){
                that.loaderDiv.hide();
                that.responseDiv.show();

                if(response.success === false){
                    that.errorDiv.show();
                    that.errorText.text(response.error);
                    that.dateElement.html("");
                    that.preZipText.hide();

                }else{

                    that.dateElement.html(response.estimatedDelivery);
                    that.preZipText.show();
                    that.preZipText.show();
                    that.zipElement.html(response.zipcode);
                    that.countryElement.html(response.country);
                    that.estimationsDiv.hide();
                    that.errorDiv.hide();
                    that.errorText.text('');
                }


            });

    };


    return wesupplyestimations;

});