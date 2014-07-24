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

        url = url.replace(encodeURIComponent('{value}'), encodeURIComponent(req.term));
        console.log(url);

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