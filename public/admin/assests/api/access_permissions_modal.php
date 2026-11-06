<div class="alp-overlay" id="accessPermissionsOverlay" style="display:none;">
  <div class="alp-modal" role="dialog" aria-modal="true" aria-labelledby="alpTitle">
    <div class="alp-header">
      <div>
        <h3 id="alpTitle"><i class="fas fa-shield-halved"></i> Module Access Permissions</h3>
        <p id="alpSubtitle">Choose which admin modules this account can access.</p>
      </div>
      <button type="button" class="alp-close" onclick="closeAccessPermissions()"><i class="fas fa-times"></i></button>
    </div>
    <div class="alp-body">
      <div class="alp-note"><i class="fas fa-circle-info"></i><span><strong>Read</strong> lets the account view the module. <strong>Read &amp; Write</strong> also allows changes.</span></div>
      <div class="alp-table">
        <div class="alp-row alp-head"><span>Module</span><span>Permission</span></div>
        <div id="alpModuleRows"></div>
      </div>
    </div>
    <div class="alp-footer">
      <button type="button" class="alp-btn secondary" onclick="closeAccessPermissions()">Cancel</button>
      <button type="button" class="alp-btn primary" onclick="saveAccessPermissions()">Save Permissions</button>
    </div>
  </div>
</div>
<link rel="stylesheet" href="assests/css/access_permissions_modal.css">
<script src="assests/js/access_permissions_modal.js"></script>