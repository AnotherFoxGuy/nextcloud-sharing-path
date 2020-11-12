window.addEventListener('DOMContentLoaded', function(event) {
  $('#enableSharingPath').bind('change', function() {
    $.ajax(OC.generateUrl('/apps/sharingpath/settings/enable'), {
      type: 'PUT',
      data: { enabled: $(this).is(':checked') ? 'yes' : 'no' },
    });
  });
});