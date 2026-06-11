$(document).ready(function() {
 	$('.edit-button').click(function() {
 		var group = $('.edit-button').attr('group');
 		var id = $('.edit-button').attr('id');
 		var parentid = $('.edit-button').attr('dir_id');
		var owner = $('.edit-button').attr('owner');
		var user = $('.edit-button').attr('user');
 		var path = $('.edit-button').attr('path');
 		var userhomeurl = $('.edit-button').attr('host');
 		var siteFolder = $('.edit-button').attr('folder') || '';
 		var pathArr = path.split('/');
 		pathArr.pop();
 		var dir = siteFolder + (pathArr.length ? '/' + pathArr.join('/') : '');
 		// Open the folder holding the current page in the Files app
 		window.location.href = userhomeurl+'/index.php/apps/files/?dir='+encodeURIComponent(dir);
	});
 	// If a div.toc is in the twig and a toc is in the md, move div.toc in place.
 	$('toc').replaceWith($('div#toc').first());
});		

