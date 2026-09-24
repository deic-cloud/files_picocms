/**
 * Click-to-enlarge for images with the .modal-image class, on every site
 * (css/site-images.css has the sizes). A theme with its own viewer keeps it,
 * so a click never opens two: the blog and team themes put a .modal element
 * right after each such image, and the documentation theme opens every image
 * in its lightbox (ekko-lightbox, a jQuery plugin).
 */
(function () {
	'use strict';
	var open = null;

	function close() {
		if (open) { open.remove(); open = null; }
		document.removeEventListener('keydown', onKey, true);
	}
	function onKey(e) { if (e.key === 'Escape') { e.preventDefault(); close(); } }

	function show(img) {
		close();
		var box = document.createElement('div');
		box.className = 'sd-lightbox';
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-modal', 'true');
		var big = document.createElement('img');
		big.src = img.currentSrc || img.src;
		big.alt = img.alt || '';
		var x = document.createElement('button');
		x.type = 'button';
		x.className = 'sd-lightbox-close';
		x.setAttribute('aria-label', 'Close');
		x.textContent = '×';
		box.appendChild(x);
		box.appendChild(big);
		if (img.alt) {
			var cap = document.createElement('div');
			cap.className = 'sd-lightbox-caption';
			cap.textContent = img.alt;
			box.appendChild(cap);
		}
		box.addEventListener('click', close);
		document.addEventListener('keydown', onKey, true);
		document.body.appendChild(box);
		open = box;
		x.focus();
	}

	document.addEventListener('click', function (e) {
		var img = e.target && e.target.closest ? e.target.closest('img') : null;
		if (!img) { return; }
		var wrap = img.closest('.modal-image');
		if (!img.classList.contains('modal-image') && !wrap) { return; }
		if (window.jQuery && window.jQuery.fn && window.jQuery.fn.ekkoLightbox) { return; }   // documentation theme
		// The theme's own viewer (blog/team): a .modal right after the image.
		var next = (img.classList.contains('modal-image') ? img : wrap).nextElementSibling;
		if (next && next.classList.contains('modal')) { return; }
		if (img.closest('a')) { return; }   // a linked image goes where it links
		e.preventDefault();
		show(img);
	});
})();
