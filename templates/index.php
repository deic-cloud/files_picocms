<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */
$serverRoot  = \OC::$WEBROOT;
$sites       = $_['sites'];
$email       = $_['email'];
$servePublic = $_['serve_public'];
$urlPrefix   = $_['url_prefix'] ?? '/remote.php/files_picocms';
?>
<div id="app-content">
<div id="picocms-app" data-url-prefix="<?php p($urlPrefix); ?>">

	<div class="picocms-header">
		<h2><?php p($l->t('Your websites')); ?></h2>
		<button id="picoNewSiteBtn" class="primary"><?php p($l->t('+ New website')); ?></button>
	</div>

	<!-- Site list -->
	<div id="picoSiteTableWrap">
	<table id="picoSiteTable">
		<thead>
			<tr>
				<th><?php p($l->t('Folder')); ?></th>
				<th><?php p($l->t('Site name (URL slug)')); ?></th>
				<th><?php p($l->t('Address')); ?></th>
				<th><?php p($l->t('Actions')); ?></th>
			</tr>
		</thead>
		<tbody id="picoSiteList">
			<?php foreach ($sites as $site): ?>
			<tr class="picoSiteRow" data-path="<?php p($site['path']); ?>">
				<td>
					<a href="<?php p($serverRoot); ?>/index.php/apps/files?dir=<?php p($site['path']); ?>"
					   title="<?php p($l->t('Browse site files in Nextcloud')); ?>">
						<?php p($site['path']); ?>
					</a>
				</td>
				<td>
					<input class="picoSiteName" type="text"
					       value="<?php p($site['site']); ?>"
					       title="<?php p($l->t('URL slug — edit to rename')); ?>" />
				</td>
				<td>
					<a href="<?php p($serverRoot . $urlPrefix); ?>/sites/<?php p(urlencode($site['site'])); ?>"
					   target="_blank" rel="noopener"
					   title="<?php p($l->t('Open the website in a new tab')); ?>">
						<?php p($urlPrefix . '/sites/' . $site['site']); ?>
					</a>
				</td>
				<td class="picoActions">
					<button class="picoManageBtn" data-path="<?php p($site['path']); ?>"
					        title="<?php p($l->t('Edit site configuration (_config.md)')); ?>">
						<?php p($l->t('Manage')); ?>
					</button>
					<button class="picoDeleteBtn" data-path="<?php p($site['path']); ?>"
					        title="<?php p($l->t('Stop serving this folder as a website')); ?>">
						<?php p($l->t('Remove')); ?>
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>

	<?php if (empty($sites)): ?>
	<p id="picoNoSites"><?php p($l->t('No websites yet. Use "New website" to get started.')); ?></p>
	<?php endif; ?>

	<!-- Public page section -->
	<div id="picoPublicSection">
		<h3><?php p($l->t('Personal public page')); ?></h3>
		<?php if ($email): ?>
		<?php $publicUrl = $serverRoot . $urlPrefix . '/users/' . $email; ?>
		<label>
			<input type="checkbox" id="picoServePublic"<?php if ($servePublic) echo ' checked'; ?> />
			<?php p($l->t('Serve your /public folder as a website at the address below')); ?>
		</label>
		<p class="picoHint"><?php p($l->t('Nothing is served until your /public folder contains a website. The easiest way to create one is via the')); ?> <a href="#" id="picoHintWizard"><?php p($l->t('website wizard')); ?></a>.</p>
		<p>
			<?php if ($servePublic): ?>
			<a href="<?php p($serverRoot . $urlPrefix); ?>/users/<?php p(urlencode($email)); ?>"
			   target="_blank" rel="noopener" title="<?php p($l->t('Open your public page in a new tab')); ?>">
				<?php p($publicUrl); ?>
			</a>
			<?php else: ?>
			<span style="color:#999;"><?php p($publicUrl); ?></span>
			<em style="color:#999;"> — <?php p($l->t('not served until enabled')); ?></em>
			<?php endif; ?>
		</p>
		<?php else: ?>
		<p><?php p($l->t('Set an email address in your personal settings to get a personal public page URL.')); ?></p>
		<?php endif; ?>
	</div>

	<!-- Add site manually (advanced) -->
	<div id="picoAddManual">
		<h3><?php p($l->t('Serve an existing folder')); ?></h3>
		<p><?php p($l->t('Point to a folder that already contains Markdown files.')); ?></p>
		<div class="picoRow">
			<input type="text" id="picoAddPath" placeholder="<?php p($l->t('/folder/path')); ?>" />
			<button id="picoAddPathBrowse" class="button"><?php p($l->t('Browse')); ?></button>
			<input type="text" id="picoAddName" placeholder="<?php p($l->t('site-name')); ?>" />
			<button id="picoAddBtn" disabled><?php p($l->t('Serve')); ?></button>
		</div>
	</div>

	<!-- New website wizard dialog -->
	<div id="picoWizardDialog" style="display:none;">
		<div class="picoWizardInner">
			<h3><?php p($l->t('New website')); ?></h3>
			<p><?php p($l->t('Choose a template to populate a new folder with sample content.')); ?></p>

			<div id="picoWizardTypes">
				<label>
					<input type="radio" name="pico_type" value="blog-profile"
					       data-folder="/public" data-content="/sample-content/blog/profile.md"
					       data-destination="index.md" data-theme="blog"
					       data-copy-themes="no" checked />
					<?php p($l->t('Public profile page')); ?>
				</label>
				<label>
					<input type="radio" name="pico_type" value="blog"
					       data-folder="/blog" data-content="/sample-content/blog"
					       data-destination="" data-theme="blog"
					       data-copy-themes="no" />
					<?php p($l->t('Blog')); ?>
				</label>
				<label>
					<input type="radio" name="pico_type" value="team"
					       data-folder="/team" data-content="/sample-content/team"
					       data-destination="" data-theme="team"
					       data-copy-themes="no" />
					<?php p($l->t('Team site')); ?>
				</label>
				<label>
					<input type="radio" name="pico_type" value="doc"
					       data-folder="/documentation" data-content="/sample-content/doc"
					       data-destination="" data-theme="documentation"
					       data-copy-themes="no" />
					<?php p($l->t('Documentation')); ?>
				</label>
				<label>
					<input type="radio" name="pico_type" value="default"
					       data-folder="/website" data-content="/sample-content/doc"
					       data-destination="" data-theme="default"
					       data-copy-themes="no" />
					<?php p($l->t('Default Pico')); ?>
				</label>
			</div>

			<div class="picoRow">
				<label><?php p($l->t('Destination folder:')); ?></label>
				<input type="text" id="picoWizardFolder" value="/public" />
				<button id="picoWizardFolderBrowse" class="button"><?php p($l->t('Browse')); ?></button>
			</div>

			<div class="picoWizardActions">
				<button id="picoWizardCreate" class="primary"><?php p($l->t('Create')); ?></button>
				<button id="picoWizardCancel"><?php p($l->t('Cancel')); ?></button>
				<span id="picoWizardMsg"></span>
			</div>
		</div>
	</div>

	<!-- Config editor dialog -->
	<div id="picoConfigDialog" style="display:none;">
		<div class="picoWizardInner">
			<h3><?php p($l->t('Site config')); ?></h3>
			<p id="picoConfigTitle" style="font-family:monospace;font-size:13px;color:#666;margin:0 0 8px;"></p>
			<textarea id="picoConfigContent" rows="22" style="width:100%;font-family:monospace;font-size:13px;box-sizing:border-box;"></textarea>
			<div class="picoWizardActions">
				<button id="picoConfigSave" class="primary"><?php p($l->t('Save')); ?></button>
				<button id="picoConfigCancel"><?php p($l->t('Cancel')); ?></button>
				<span id="picoConfigMsg"></span>
			</div>
		</div>
	</div>

</div><!-- #picocms-app -->
</div><!-- #app-content -->
