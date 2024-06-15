(function($) {
  $('#bannerimg').on('click', function(e) {
    e.preventDefault();
    $('input[name="banner"]').val('');
    name = $(this).attr('identi');
    $('#uploader').html('<input type="file" name="'+name+'" class="newbanner"/>').find('input').trigger('click');
  });
  $(document).on('change', '#FOG_CLIENT_BANNER_IMAGE', function(e) {
    filename = this.value;
    filename = filename.replace(/\\/g, '/').replace(/.*\//, "");
    $('input[name="banner"]').val(filename);
  });
  tokenreset();
})(jQuery);
function setTimeoutElement() {
  $('button[type="submit"]:not(#importbtn, #export, #upload, #Rebranding, #install), #menuSet, #hideSet, #exitSet, #advSet, button[name="saveform"], button[name="delform"], #deletecu').each(function(e) {
    if ($(this).is(':visible')) {
      $(this).on('click', function(e) {
        form = $(this).parents('form');
        validator = form.validate(validatorOpts);
      });
    }
  });
  setTimeout(setTimeoutElement, 1000);
}
