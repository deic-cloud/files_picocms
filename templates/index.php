<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */
$serverRoot  = \OC::$WEBROOT;
$sites       = $_['sites'];
$email       = $_['email'];
$servePublic = $_['serve_public'];
?>
<div id="picocms-app">

	<div class="picocms-header">
		<h2><?php p($l->t('Your websites')); ?></h2>
		<button id="picoNewSiteBtn" class="primary"><?php p($l->t('+ New website')); ?></button>
	</div>

	<!-- Site list -->
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
					<a href="<?php p($serverRoot); ?>/index.php/apps/files?dir=<?php p($site['path']); ?>">
						<?php p($site['path']); ?>
					</a>
				</td>
				<td>
					<input class="picoSiteName" type="text"
					       value="<?php p($site['site']); ?>"
					       title="<?php p($l->t('Edit to rename')); ?>" />
				</td>
				<td>
					<a href="<?php p($serverRoot); ?>/remote.php/files_picocms/sites/<?php p(urlencode($site['site'])); ?>"
					   target="_blank" rel="noopener">
						<?php p('/remote.php/files_picocms/sites/' . $site['site']); ?>
					</a>
				</td>
				<td class="picoActions">
					<button class="picoManageBtn" data-path="<?php p($site['path']); ?>">
						<?php p($l->t('Manage')); ?>
					</button>
					<button class="picoDeleteBtn" data-path="<?php p($site['path']); ?>">
						<?php p($l->t('Remove')); ?>
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if (empty($sites)): ?>
	<p id="picoNoSites"><?php p($l->t('No websites yet. Use "New website" to get started.')); ?></p>
	<?php endif; ?>

	<!-- Public page section -->
	<div id="picoPublicSection">
		<h3><?php p($l->t('Personal public page')); ?></h3>
		<p><?php p($l->t('Serve your /public folder as a public page at a personal URL.')); ?></p>
		<label>
			<input type="checkbox" id="picoServePublic"<?php if ($servePublic) echo ' checked'; ?> />
			<?php p($l->t('Enable personal public page')); ?>
		</label>
		<?php if ($email && $servePublic): ?>
		<p>
			<?php p($l->t('Your public page:')); ?>
			<a href="<?php p($serverRoot); ?>/remote.php/files_picocms/users/<?php p(urlencode($email)); ?>"
			   target="_blank" rel="noopener">
				<?php p($serverRoot . '/remote.php/files_picocms/users/' . $email); ?>
			</a>
		</p>
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
			<button id="picoAddBtn"><?php p($l->t('Serve')); ?></button>
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
					       data-destination="index.md" data-theme="deic-blog"
					       data-copy-themes="no" checked />
					<?php p($l->t('Single public profile page')); ?>
				</label>
				<label>
					<input type="radio" name="pico_type" value="blog"
					       data-folder="/blog" data-content="/sample-content/blog"
					       data-destination="content" data-theme="deic-blog"
					       data-copy-themes="yes" />
					<?php p($l->t('Blog')); ?>
				</label>
				<label>
					<input type="radio" name="pico_type" value="wiki"
					       data-folder="/wiki" data-content="/sample-content/wiki"
					       data-destination="content" data-theme="deic-wiki"
					       data-copy-themes="yes" />
					<?php p($l->t('Wiki')); ?>
				</label>
				<label>
					<input type="radio" name="pico_type" value="doc"
					       data-folder="/documentation" data-content="/sample-content/doc"
					       data-destination="content" data-theme="deic-doc"
					       data-copy-themes="yes" />
					<?php p($l->t('Documentation')); ?>
				</label>
				<label>
					<input type="radio" name="pico_type" value="default"
					       data-folder="/website" data-content="/sample-content/doc"
					       data-destination="content" data-theme="default"
					       data-copy-themes="yes" />
					<?php p($l->t('Default Pico')); ?>
				</label>
			</div>

			<div class="picoRow">
				<label><?php p($l->t('Destination folder:')); ?></label>
				<input type="text" id="picoWizardFolder" value="/public" />
			</div>

			<div class="picoWizardActions">
				<button id="picoWizardCreate" class="primary"><?php p($l->t('Create')); ?></button>
				<button id="picoWizardCancel"><?php p($l->t('Cancel')); ?></button>
				<span id="picoWizardMsg"></span>
			</div>
		</div>
	</div>

</div><!-- #picocms-app -->
