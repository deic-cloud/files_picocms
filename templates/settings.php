<?php /** @var array $_ */ ?>
<div class="section" id="picoAdminSettings">
	<h2><?php p($l->t('Websites')); ?></h2>
	<p><?php p($l->t('Sample site folder — shared with new users when they create their first site.')); ?></p>
	<div>
		<label><?php p($l->t('Owner of sample site folder:')); ?></label>
		<input type="text" id="sampleDirOwner" value="<?php p($_['samplesiteowner']); ?>" />
	</div>
	<div>
		<label><?php p($l->t('Path of sample site folder:')); ?></label>
		<input type="text" id="sampleDirPath" value="<?php p($_['samplesitepath']); ?>" />
	</div>
	<button id="sampleDirSubmit"><?php p($l->t('Save')); ?></button>
	<span id="ownerChange" class="msg"></span>
</div>

<div class="section" id="picoContentUser">
	<h2><?php p($l->t('Public pages')); ?></h2>
	<p><?php p($l->t('The user whose files host the public pages (front page, terms, blog, documentation). Default pages are installed for this user if they have not been written yet.')); ?></p>
	<div>
		<label><?php p($l->t('Content host user:')); ?></label>
		<input type="text" id="contentUser" value="<?php p($_['contentuser']); ?>" />
	</div>
	<button id="contentUserSubmit"><?php p($l->t('Save')); ?></button>
	<span id="contentUserMsg" class="msg"></span>
</div>
