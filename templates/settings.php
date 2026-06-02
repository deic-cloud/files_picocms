<?php /** @var array $_ */ ?>
<div class="section" id="picoAdminSettings">
	<h2><?php p($l->t('Pico CMS')); ?></h2>
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
