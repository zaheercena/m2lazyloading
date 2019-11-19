define([
	'jquery',
    'mage/translate'
], function ($) {
	"use strict";

	var massUpdater = massUpdater || {};

    massUpdater.updateButton = $('#massupdate-btn');
    massUpdater.formKey = $('#massupdate_form_key');
    massUpdater.msgContainer = $('.massupdate-msg');
    massUpdater.progressContainer = $('.massupdate-progress');
    massUpdater.percentBar = $('.massupdate-progress .bar');
    massUpdater.orderNumber = 0;
    massUpdater.batchSize = 1000;

    massUpdater.totalOrders = 0;
    massUpdater.step = 0;
    massUpdater.percent = 0;
    massUpdater.ordersFetched = 0;

    massUpdater.initialize = function (orderNumbersUrl, ordersUpdateUrl, batchSize) {
		var that = this;
		that.orderNumbersUrl = orderNumbersUrl;
		that.ordersUpdateUrl = ordersUpdateUrl;
        that.batchSize = parseInt(batchSize);
		that.getOrderNumber();
		that.updateButton.click(function() {
			that.disableButton();
            that.showProgress();
            that.totalOrders = that.orderNumber;
            that.step = 0;
            that.percent = 0;
            that.ordersFetched = 0;
            that.startUpdate();
		});
	};

    massUpdater.enableButton = function() {
        massUpdater.updateButton.prop('disabled', false);
	};

    massUpdater.disableButton = function() {
        massUpdater.updateButton.prop('disabled', true);
    };

    massUpdater.appendMessage = function (message) {
        massUpdater.msgContainer.append("<p>" + message + "</p>");
    };

    massUpdater.showProgress = function () {
        massUpdater.progressContainer.show();
    };

    massUpdater.updateProgress = function(percent) {
        var percentValue = percent + "%";
        massUpdater.percentBar.css('width', percentValue);
        massUpdater.percentBar.html(percentValue);
    };

    massUpdater.getOrderNumber = function() {
        var that = this;
        $.ajax({
            url: that.orderNumbersUrl,
            data: {
                'form_key' : that.formKey.val()
            },
            type: "POST",
            dataType: 'json'
        }).done(function (data) {
            if (data.success) {
                that.orderNumber = data.ordersNr;
                that.appendMessage("Orders found: " + that.orderNumber);
                that.appendMessage("Click on the Run Mass Update Button to start the process.");
                if (that.orderNumber) {
                    that.enableButton();
                }
            } else {
                that.appendMessage(data.msg);
            }
        });
	};

    massUpdater.startUpdate = function() {
        var that = this;
        if (that.totalOrders > 0) {
            $.ajax({
                url: that.ordersUpdateUrl,
                data: {
                    'form_key' : that.formKey.val(),
                    'limit' : that.batchSize,
                    'offset' : that.step
                },
                type: "POST",
                dataType: 'json'
            }).done(function (data) {

                if (that.totalOrders < that.batchSize){
                    that.ordersFetched += that.totalOrders;
                } else {
                    that.ordersFetched += that.batchSize;
                }

                that.percent = parseInt((100 * that.ordersFetched) / that.orderNumber);
                that.updateProgress(that.percent);
                that.totalOrders -= that.batchSize;
                that.step = that.step+1;

                if (data.success) {
                    that.appendMessage(that.ordersFetched + " orders already updated ...");
                } else {
                    that.appendMessage(data.msg);
                }

                that.startUpdate();
            });
        } else {
            that.enableButton();
            that.appendMessage("Mass Update is finalized.");
        }
    };

    return massUpdater;
});