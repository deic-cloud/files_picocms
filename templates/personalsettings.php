<?php
/** @var array $_ */
$email      = $_['email'];
$serverRoot = \OC::$WEBROOT;
?>
<div class="section" id="filesPicoSettings">
	<h2><?php p($l->t('Site folders')); ?></h2>
	<p><?php p($l->t("Folders containing Markdown files (.md) that will be served as websites by Pico CMS.")); ?></p>
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
			<?php p($l->t('Serve /public folder as personal public page')); ?>
			<?php if ($email): ?>
			(<a href="<?php p($serverRoot); ?>/remote.php/files_picocms/users/<?php p($email); ?>"><?php p($serverRoot . '/remote.php/files_picocms/users/' . $email); ?></a>)
			<?php endif; ?>
		</label>
	</div>

	<br />
	<button id="picoWizardBtn"><?php p($l->t('Website wizard')); ?></button>

	<!-- Wizard dialog -->
	<div id="picoWizardDialog" style="display:none;">
		<p><?php p($l->t('Click "Create" to populate a folder with sample content and create a new site.')); ?></p>
		<div>
			<label><?php p($l->t('Type:')); ?></label><br />
			<label><input type="radio" name="pico_type" value="wiki"
				data-folder="/wiki" data-content="/sample-content/wiki" data-destination="content"
				data-theme="deic-wiki" data-copy-themes="yes" /> <?php p($l->t('Wiki')); ?></label><br />
			<label><input type="radio" name="pico_type" value="blog-profile"
				data-folder="/public" data-content="/sample-content/blog/profile.md" data-destination="index.md"
				data-theme="blog" data-copy-themes="no" checked /> <?php p($l->t('Single public profile page')); ?></label><br />
			<label><input type="radio" name="pico_type" value="blog"
				data-folder="/blog" data-content="/sample-content/blog" data-destination="content"
				data-theme="blog" data-copy-themes="yes" /> <?php p($l->t('Blog')); ?></label><br />
			<label><input type="radio" name="pico_type" value="doc"
				data-folder="/documentation" data-content="/sample-content/doc" data-destination="content"
				data-theme="documentation" data-copy-themes="yes" /> <?php p($l->t('Documentation')); ?></label><br />
			<label><input type="radio" name="pico_type" value="default"
				data-folder="/website" data-content="/sample-content/doc" data-destination="content"
				data-theme="default" data-copy-themes="yes" /> <?php p($l->t('Default Pico')); ?></label>
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
