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
      <label class="alp-special">
        <input type="checkbox" id="alpApproveEnrollment">
        <span><i class="fas fa-user-check"></i></span>
        <div><strong>Approve Enrollment Requests</strong><small>Allow this account to approve pending student enrollment requests.</small></div>
      </label>
    </div>
    <div class="alp-footer">
      <button type="button" class="alp-btn secondary" onclick="closeAccessPermissions()">Cancel</button>
      <button type="button" class="alp-btn primary" onclick="saveAccessPermissions()">Save Permissions</button>
    </div>
  </div>
</div>
<style>
.alp-overlay{position:fixed;inset:0;background:rgba(0,0,0,.48);backdrop-filter:blur(5px);z-index:10050;display:flex;align-items:center;justify-content:center;padding:20px}
.alp-modal{width:min(650px,96vw);max-height:90vh;overflow:hidden;background:#fff;border-radius:18px;box-shadow:0 25px 80px rgba(0,0,0,.25);font-family:'DM Sans','Segoe UI',sans-serif}
.alp-header{display:flex;align-items:flex-start;gap:15px;padding:24px 26px 18px;border-bottom:1px solid #e5e7eb}
.alp-header>div:first-child{flex:1}.alp-header h3{margin:0 0 5px;color:#124029;font-size:1.12rem}.alp-header p{margin:0;color:#6b7280;font-size:.82rem}
.alp-close{border:0;background:#f3f4f6;width:34px;height:34px;border-radius:9px;cursor:pointer;color:#6b7280}
.alp-body{padding:20px 26px;overflow:auto;max-height:60vh}.alp-note{display:flex;gap:9px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:11px 13px;font-size:.8rem;color:#365247;margin-bottom:15px}
.alp-table{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.alp-row{display:grid;grid-template-columns:1fr 190px;align-items:center;gap:12px;padding:12px 14px;border-bottom:1px solid #eef0f2}.alp-row:last-child{border-bottom:0}.alp-head{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;background:#f8faf9;font-weight:700}.alp-module{display:flex;align-items:center;gap:10px}.alp-module i{width:28px;height:28px;border-radius:8px;background:#eef7f1;color:#124029;display:grid;place-items:center}.alp-select{width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;background:#fff}
.alp-special{display:flex;align-items:center;gap:11px;margin-top:15px;padding:13px;border:1px solid #fde68a;background:#fffbeb;border-radius:11px;cursor:pointer}.alp-special>input{width:17px;height:17px}.alp-special>span{width:30px;height:30px;border-radius:8px;background:#fef3c7;color:#b45309;display:grid;place-items:center}.alp-special div{display:flex;flex-direction:column}.alp-special strong{font-size:.83rem;color:#374151}.alp-special small{font-size:.72rem;color:#6b7280;margin-top:2px}
.alp-footer{display:flex;justify-content:flex-end;gap:9px;padding:17px 26px;border-top:1px solid #e5e7eb}.alp-btn{border:0;border-radius:9px;padding:10px 16px;font-weight:700;cursor:pointer}.alp-btn.secondary{background:#f3f4f6;color:#374151}.alp-btn.primary{background:#124029;color:#fff}
@media(max-width:600px){.alp-row{grid-template-columns:1fr}.alp-head{display:none}.alp-body{padding:16px}.alp-header,.alp-footer{padding-left:16px;padding-right:16px}}
</style>
<script>
(function(){
  const modules = [["dashboard","Dashboard","fa-th-large"],["users","User Management","fa-users"],["courses","Classes & Subjects","fa-book"],["enrollment","Enrollment","fa-user-plus"],["announcements","Announcements","fa-bullhorn"],["analytics","System Analytics","fa-chart-line"],["settings","Settings","fa-cog"]];
  let targetFormId = null;
  let pendingPermissions = {};
  let targetAccessLevel = 'limited';

  function renderRows(){
    const box=document.getElementById('alpModuleRows');
    box.innerHTML=modules.map(([key,label,icon])=>{
      const cfg=pendingPermissions[key]||{};
      const val=targetAccessLevel==='read_only' ? (cfg.permission?'read':'none') : (cfg.permission||'none');
      return '<div class="alp-row"><div class="alp-module"><i class="fas '+icon+'"></i><span>'+label+'</span></div>'+
        '<select class="alp-select" data-module="'+key+'">'+
        '<option value="none" '+(val==='none'?'selected':'')+'>No Access</option>'+
        '<option value="read" '+(val==='read'?'selected':'')+'>Read</option>'+
        '<option value="write" '+(val==='write'?'selected':'')+'>Read &amp; Write</option></select></div>';
    }).join('');
    document.getElementById('alpApproveEnrollment').checked=!!(pendingPermissions.enrollment&&pendingPermissions.enrollment.can_approve_enrollment);
    document.getElementById('alpApproveEnrollment').disabled=targetAccessLevel==='read_only' || !['read','write'].includes(document.querySelector('[data-module="enrollment"]')?.value||'none');
  }

  window.openAccessPermissions=function(formId, level, json){
    targetFormId=formId; targetAccessLevel=level||'limited';
    try{ pendingPermissions=JSON.parse(json||'{}'); }catch(e){ pendingPermissions={}; }
    if(targetAccessLevel==='full'){
      pendingPermissions={};
      modules.forEach(([key])=>pendingPermissions[key]={permission:'write',can_approve_enrollment:key==='enrollment'});
    }
    renderRows();
    document.getElementById('accessPermissionsOverlay').style.display='flex';
  };
  window.closeAccessPermissions=function(){document.getElementById('accessPermissionsOverlay').style.display='none';};
  window.saveAccessPermissions=function(){
    if(targetAccessLevel!=='full'){
      document.querySelectorAll('#alpModuleRows select').forEach(s=>{
        if(s.value==='none') delete pendingPermissions[s.dataset.module];
        else pendingPermissions[s.dataset.module]={permission:targetAccessLevel==='read_only'?'read':s.value,can_approve_enrollment:false};
      });
      const e=document.querySelector('#alpModuleRows select[data-module="enrollment"]');
      pendingPermissions.enrollment=pendingPermissions.enrollment||{};
      pendingPermissions.enrollment.can_approve_enrollment=document.getElementById('alpApproveEnrollment').checked && !!e && e.value!=='none' && targetAccessLevel!=='read_only';
      if(!Object.keys(pendingPermissions).length){ alert('Select at least one module.'); return; }
    }
    const form=document.getElementById(targetFormId);
    if(form) form.querySelector('[name="permissions_json"]').value=JSON.stringify(pendingPermissions);
    closeAccessPermissions();
  };
  window.handleAccessLevelChange=function(select,formId){
    const level=select.value;
    const form=document.getElementById(formId);
    if(!form) return;
    const hidden=form.querySelector('[name="permissions_json"]');
    if(level==='full'){
      const all={}; modules.forEach(([key])=>all[key]={permission:'write',can_approve_enrollment:key==='enrollment'});
      hidden.value=JSON.stringify(all);
      return;
    }
    openAccessPermissions(formId,level,hidden.value);
  };
  document.addEventListener('DOMContentLoaded',()=>{
    const addLevel=document.querySelector('#aumForm [name="access_level"]');
    if(addLevel) addLevel.addEventListener('change',()=>handleAccessLevelChange(addLevel,'aumForm'));
    const editLevel=document.getElementById('editAccessLevel');
    if(editLevel) editLevel.addEventListener('change',()=>handleAccessLevelChange(editLevel,'editUserForm'));
    document.getElementById('aumForm')?.addEventListener('submit',function(e){
      const role=this.querySelector('[name="role"]')?.value;
      const level=this.querySelector('[name="access_level"]')?.value;
      if(role==='admin' && level!=='full' && !this.querySelector('[name="permissions_json"]').value){
        e.preventDefault(); e.stopImmediatePropagation(); openAccessPermissions('aumForm',level,'{}');
      }
    },true);
    document.getElementById('editUserForm')?.addEventListener('submit',function(e){
      const role=this.querySelector('[name="role"]')?.value;
      const level=this.querySelector('[name="access_level"]')?.value;
      if(role==='admin' && level!=='full' && !this.querySelector('[name="permissions_json"]').value){
        e.preventDefault(); e.stopImmediatePropagation(); openAccessPermissions('editUserForm',level,'{}');
      }
    },true);
  });
})();
</script>