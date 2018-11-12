(function($, Drupal) {

  Drupal.behaviors.IndieWebNodeType = {
    attach: function (context, settings) {

      // Display the action in the vertical tab summary.
      $(context).find('.indieweb-node-form').drupalSetSummary(function(context) {
        var $field = $('.indieweb-post-context-field', context).val();
        if ($field.length > 0) {
          $return = "Link field: " + $field;
        }
        else {
          $return = "No link field";
        }
        return Drupal.checkPlain($return);
      });

    }
  }

})(jQuery, Drupal);
