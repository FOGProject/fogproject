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
})(jQuery);
