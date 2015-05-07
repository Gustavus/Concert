Extend.add('autocompleteUser', function() {
  $('.autocompleteUser').each(function() {
    var $this = $(this);
    var thisObj = this;
    $this.autocomplete({
      source: function(req, add) {
        var parent   = Gustavus.FormBuilder.getParentFBElement(thisObj);
        var children = Gustavus.FormBuilder.getChildrenFBElements(parent);

        var role = $(children).find('select').val();
        var url = $this.data('autocompletepath');

        url = url.replace('{value}', encodeURIComponent(req.term));

        $.getJSON(
          url,
          function(data) {
            add(data);
          }
        );
      }
    })
  });
});

$(function() {
  Extend.apply('autocompleteUser');
});