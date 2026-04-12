;(function ($) {
    "use strict";

    // $("#metismenu").metisMenu();

    $(".menu-toggler").on("click", function() {
        $('body').toggleClass('_toggle');
    });

    $(".menu-close").on("click", function() {
        $('body').removeClass('_toggle');
    });

    $(".cp-user-sidebar-toggler-s2").on("click", function() {
        $('body').toggleClass('_sidebar-cllopse');
    });




    // $('.cp-user-sidebar-toggler, .mb-sidebar-toggler').on('click', function () {
    //     $('.cp-user-sidebar').toggleClass('sidebar-show');
    // });

    $(".cp-user-deposit-card-select ul").on("click", ".init", function () {
        $(this).closest("ul").children('li:not(.init)').toggle();
    });

    var allOptions = $(".cp-user-deposit-card-select ul").children('li:not(.init)');
    $(".cp-user-deposit-card-select ul").on("click", "li:not(.init)", function () {
        allOptions.removeClass('selected');
        $(this).addClass('selected');
        $(".cp-user-deposit-card-select ul").children('.init').html($(this).html());
        allOptions.toggle();
    });


    // $(window).resize(function() {

    //     if ($(window).width() <= 769) {

    //         $('.cp-user-top-bar-logo').hide();
    //         $('.cp-user-top-bar').addClass('content-expend');
    //         $('.cp-user-main-wrapper').addClass('content-expend');
    //         $('.cp-user-sidebar').addClass('sidebar-hide');

    //     }
    //     if ($(window).width() <= 426) {
    //         $('.cp-user-top-bar-logo').show();
    //     }

    // });

    $(document).ready(function () {
        $('.scrollbar-inner').scrollbar();
    });



}(jQuery));


;
(function($) {
    "use strict";

    $("#metismenu").metisMenu();

    $('.menu-bars').on('click', function() {
        $('.cp-user-sidebar').toggleClass('cp-user-sidebar-hide ');
        $('.cp-user-top-bar').toggleClass('cp-user-content-expend');
        $('.cp-user-main-wrapper').toggleClass('cp-user-content-expend');
        $('.cp-user-logo').toggleClass('cp-user-logo-hide');
    });

    $(window).resize(function() {
        sidebarMenuCollpase();
    });

    function sidebarMenuCollpase() {
        if ($(window).width() <= 769) {

            // $('.cp-user-logo').hide();
            $('.cp-user-top-bar').addClass('cp-user-content-expend');
            $('.cp-user-main-wrapper').addClass('cp-user-content-expend');
            $('.cp-user-sidebar').addClass('cp-user-sidebar-hide ');

            $('.menu-bars').on('click', function () {
                $('.cp-user-main-wrapper').toggleClass('cp-user-content-expend');
            });

        }
        if ($(window).width() <= 426) {
            $('.cp-user-logo').show();
            $('.cp-user-top-bar').addClass('cp-user-content-expend');
            $('.cp-user-main-wrapper').addClass('cp-user-content-expend');
            $('.cp-user-sidebar').addClass('cp-user-sidebar-hide ');

            $('.menu-bars').on('click', function () {
                $('.cp-user-main-wrapper').toggleClass('cp-user-content-expend');
            });
        }
    }
    sidebarMenuCollpase();


    $("#select-all").click(function () {
        $('input:checkbox').not(this).prop('checked', this.checked);
    });

}(jQuery));
