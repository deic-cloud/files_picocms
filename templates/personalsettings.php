<?php
/** @var array $_ */
$email      = $_['email'];
$serverRoot = \OC::$WEBROOT;
?>
<div class="section" id="filesPicoSettings">
	<h2><?php p($l->t('Site folders')); ?></h2>
	<p><?php p($l->t("Folders containing Markdown files (.md) that will be served as websites.")); ?></p>
	<p><?php p($l->t("A site is served at: remote.php/files_picocms/sites/{name}")); ?></p>
	<br />

	<div id="filesPicoSiteFoldersList">
		<?php foreach ($_['site_folders'] as $site): ?>
		<div class="siteFolder" data-path="<?php p($site['path']); ?>">
			<span class="folder">
				<a href="<?php p($serverRoot); ?>/index.php/apps/files/?dir=<?php p($site['path']); ?>">
					<label><?php p($site['path']); ?></label>
				</a>
			</span>
			<span class="url">
				<label><?php p($serverRoot . '/remote.php/files_picocms/sites/'); ?></label>
				<input type="text" value="<?php p($site['site']); ?>" autocomplete="off" />
			</span>
			<button class="remove-site-btn" data-path="<?php p($site['path']); ?>">-</button>
		</div>
		<?php endforeach; ?>
	</div>

	<div class="addSiteFolder">
		<input type="text" id="newSiteFolderPath" placeholder="<?php p($l->t('/folder/path')); ?>" />
		<button id="addSiteFolderBtn">+</button>
	</div>

	<br />

	<div>
		<label>
			<input type="checkbox" id="servePublicUrl"<?php if ($_['serve_public_url']) echo ' checked'; ?> />
			<?php p($l->t('Serve your /public folder as a website at the address below')); ?>
		</label>
		<p class="picoHint"><?php p($l->t('Nothing is served until your /public folder contains a website. The easiest way to create one is via the')); ?> <a href="#" id="picoHintWizard"><?php p($l->t('website wizard')); ?></a>.</p>
		<?php if ($email): ?>
		<?php $publicUrl = $serverRoot . '/remote.php/files_picocms/users/' . $email; ?>
		<p>
			<?php if ($_['serve_public_url']): ?>
			<a href="<?php p($serverRoot); ?>/remote.php/files_picocms/users/<?php p(urlencode($email)); ?>"
			   target="_blank" rel="noopener" title="<?php p($l->t('Open your public page in a new tab')); ?>"><?php p($publicUrl); ?></a>
			<?php else: ?>
			<span style="color:#999;"><?php p($publicUrl); ?></span>
			<em style="color:#999;"> — <?php p($l->t('not served until enabled')); ?></em>
			<?php endif; ?>
		</p>
		<?php else: ?>
		<p><?php p($l->t('Set an email address in your personal settings to get a personal public page URL.')); ?></p>
		<?php endif; ?>
	</div>

	<br />
	<button id="picoWizardBtn"><?php p($l->t('Website wizard')); ?></button>

	<!-- Wizard dialog -->
	<div id="picoWizardDialog" style="display:none;">
		<p><?php p($l->t('Click "Create" to populate a folder with sample content and create a new site.')); ?></p>
		<div>
			<label><?php p($l->t('Type:')); ?></label><br />
			<label><input type="radio" name="pico_type" value="blog-profile"
				data-folder="/public" data-content="/sample-content/blog/profile.md" data-destination="index.md"
				data-theme="blog" data-copy-themes="no" checked /> <?php p($l->t('Public profile page')); ?></label><br />
			<label><input type="radio" name="pico_type" value="blog"
				data-folder="/blog" data-content="/sample-content/blog" data-destination=""
				data-theme="blog" data-copy-themes="no" /> <?php p($l->t('Blog')); ?></label><br />
			<label><input type="radio" name="pico_type" value="team"
				data-folder="/team" data-content="/sample-content/team" data-destination=""
				data-theme="team" data-copy-themes="no" /> <?php p($l->t('Team site')); ?></label><br />
			<label><input type="radio" name="pico_type" value="doc"
				data-folder="/documentation" data-content="/sample-content/doc" data-destination=""
				data-theme="documentation" data-copy-themes="no" /> <?php p($l->t('Documentation')); ?></label><br />
			<label><input type="radio" name="pico_type" value="default"
				data-folder="/website" data-content="/sample-content/doc" data-destination=""
				data-theme="default" data-copy-themes="no" /> <?php p($l->t('Default Pico')); ?></label>
		</div>
		<br />
		<p>
			<?php p($l->t('Destination folder:')); ?>
			<input type="text" id="wizardFolder" value="/public" />
		</p>
		<button id="picoWizardCreate"><?php p($l->t('Create')); ?></button>
		<button id="picoWizardCancel"><?php p($l->t('Cancel')); ?></button>
		<span id="picoWizardMsg"></span>
	</div>
</div>
