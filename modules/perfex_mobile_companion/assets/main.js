"use strict";
$("body").on("click", ".open_mobileapp, .close_mobileapp", function (e) {
    e.preventDefault();
    if (typeof $(this).data("close") == "undefined") {
        requestGet("perfex_mobile_companion/get_qr_data").done(function (response) {
            $("#mobileapp").html(response);
        });
    } else if ($(this).data("close") === true) {
        $("#mobileapp").html("");
    }
    $("#mobileapp").toggleClass("hide");
    $("body").toggleClass("noscroll");
});