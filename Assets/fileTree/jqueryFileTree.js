// jQuery File Tree Plugin
//
// Version 1.01
//
// Cory S.N. LaViska
// A Beautiful Site (http://abeautifulsite.net/)
// 24 March 2008
//
// Visit http://abeautifulsite.net/notebook.php?article=58 for more information
//
// Usage: $('.fileTreeDemo').fileTree( options, callback )
//
// Options:  root           - root folder to display; default = /
//           script         - location of the serverside AJAX file to use; default = jqueryFileTree.php
//           folderEvent    - event to trigger expand/collapse; default = click
//           expandSpeed    - default = 500 (ms); use -1 for no animation
//           collapseSpeed  - default = 500 (ms); use -1 for no animation
//           expandEasing   - easing function to use on expand (optional)
//           collapseEasing - easing function to use on collapse (optional)
//           multiFolder    - whether or not to limit the browser to one subfolder at a time
//           loadMessage    - Message to display while initial tree loads (can be HTML)
//
// History:
//
// 1.01 - updated to work with foreign characters in directory/file names (12 April 2008)
// 1.00 - released (24 March 2008)
//
// TERMS OF USE
//
// This plugin is dual-licensed under the GNU General Public License and the MIT License and
// is copyright 2008 A Beautiful Site, LLC.
//
if(jQuery) (function($){

	$.extend($.fn, {
		fileTree: function(o, h) {
			// Defaults
			if (!o) {
        var o = {};
      }
			if (o.root == undefined) {
        o.root = '/';
      }
			if (o.script == undefined) {
        o.script = 'jqueryFileTree.php';
      }
			if (o.folderEvent == undefined) {
        o.folderEvent = 'click';
      }
			if (o.expandSpeed == undefined) {
        o.expandSpeed = 500;
      }
			if (o.collapseSpeed == undefined) {
        o.collapseSpeed = 500;
      }
			if (o.expandEasing == undefined) {
        o.expandEasing = null;
      }
			if (o.collapseEasing == undefined) {
        o.collapseEasing = null;
      }
			if (o.multiFolder == undefined) {
        o.multiFolder = true;
      }
			if (o.loadMessage == undefined) {
        o.loadMessage = 'Loading...';
      }

			$(this).each(function() {

				function showTree(c, t) {
					$(c).addClass('wait');
					$(".jqueryFileTree.start").remove();
					$.get(o.script, {dir: t}, function(data) {
						$(c).find('.start').html('');
						$(c).removeClass('wait').append(data);
						if(o.root == t) $(c).find('UL:hidden').show(); else $(c).find('UL:hidden').slideDown({ duration: o.expandSpeed, easing: o.expandEasing });
						bindTree(c);
					});
				}

        // Abstracted out of bind tree by Billy Visto on 7/29/2014
        function directoryClickActions($elem) {
          if ($elem.parent().hasClass('collapsed')) {
            // Expand
            if (!o.multiFolder) {
              $elem.parent().parent().find('UL').slideUp({ duration: o.collapseSpeed, easing: o.collapseEasing });
              $elem.parent().parent().find('LI.directory').removeClass('expanded').addClass('collapsed');
            }
            $elem.parent().find('UL').remove(); // cleanup
            showTree( $elem.parent(), escape($elem.attr('rel').match( /.*\// )) );
            $elem.parent().removeClass('collapsed').addClass('expanded');
          } else {
            // Collapse
            $elem.parent().find('UL').slideUp({ duration: o.collapseSpeed, easing: o.collapseEasing });
            $elem.parent().removeClass('expanded').addClass('collapsed');
          }
        }

        /**
         * Form named success action. Modifies the attributes of the new folder directory so we can build a file path later on
         *   Added by Billy Visto on 7/29/2014
         * @param  {jQuery} $elem  The element of the new folder button
         * @param  {string} newFileName Name of the new file
         * @param  {boolean} forDirectory Whether or not this is for a new directory
         * @return {undefined}
         */
        function formNamedFunc($elem, newFileName, forDirectory) {
          if (forDirectory) {
            var searchString = 'concertNewFolder';
          } else {
            var searchString = 'concertNewFile'
            if (newFileName.indexOf('.php') <= 0) {
              newFileName += '.php';
            }
          }
          if (newFileName !== '') {
            newFileName = newFileName.replace(/\s/g, '');
            var newRel = $elem.attr('rel').replace(searchString, newFileName);

            // new folder added
            $elem.attr('rel', newRel);
            $elem.html(newFileName);
          }
        }

        /**
         * Builds the dialog to ask for the name of the new file or folder
         *   Added by Billy Visto on 7/29/2014
         * @param  {jQuery} $elem Element of the new directory button
         * @param  {boolean} forDirectory Whether or not this is for a new directory
         * @return {undefined}
         */
        function newFileDialog($elem, forDirectory) {
          $('#newFileNameSubmit').on('click', function(e) {
            e.preventDefault();
            formNamedFunc($elem, $(theFile).val(), forDirectory);
            if (forDirectory) {
              directoryClickActions($elem);
            } else {
              h($elem.attr('rel'));
            }
            $('#newFileNameDialog').dialog('close');
            $('#newFileNameDialog').dialog('destroy');
          });

          var dialogTitle = forDirectory ? 'New folder name' : 'New file name';

          $('#newFileNameDialog').dialog({
            //appendTo: $elem,
            title: dialogTitle,
            width: 300,
            draggable: false,
            modal: true,
            buttons: {
              'Create': function() {
                formNamedFunc($elem, $(theFile).val(), forDirectory);
                if (forDirectory) {
                  directoryClickActions($elem);
                } else {
                  h($elem.attr('rel'));
                }
                $(this).dialog('close');
                $(this).dialog('destroy');
              },
              Cancel: function() {
                $(this).dialog('close');
                $(this).dialog('destroy');
              }
            },
          });
        }

				function bindTree(t) {
					$(t).find('LI A').on(o.folderEvent, function(e) {
            var $this = $(this);
            e.preventDefault();
						if ($this.parent().hasClass('directory')) {
              if ($this.attr('rel').indexOf('concertNewFolder/') >= 0) {
                newFileDialog($this, true);
              } else {
                directoryClickActions($this)
              }
						} else {
              $this.parents('.fileTreeSelector').find('.selected').removeClass('selected');
              $this.addClass('selected');
              if ($this.attr('rel').indexOf('concertNewFile') >= 0) {
                newFileDialog($this, false);
              } else {
							  h($this.attr('rel'));
              }
						}
						return false;
					});
					// Prevent A from triggering the # on non-click events
					if (o.folderEvent.toLowerCase != 'click') {
            $(t).find('LI A').on('click', function(e) {
              e.preventDefault();
              return false;
            });
          }
				}
				// Loading message
				$(this).html('<ul class="jqueryFileTree start"><li class="wait">' + o.loadMessage + '<li></ul>');
				// Get the initial file list
				showTree( $(this), escape(o.root) );
			});
		}
	});

})(jQuery);