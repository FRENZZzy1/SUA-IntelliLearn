(function(){
  const modules = [["dashboard","Dashboard","fa-th-large"],["users","User Management","fa-users"],["courses","Classes & Subjects","fa-book"],["announcements","Announcements","fa-bullhorn"],["analytics","System Analytics","fa-chart-line"],["settings","Settings","fa-cog"]];
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
  }

  window.openAccessPermissions=function(formId, level, json){
    targetFormId=formId; targetAccessLevel=level||'limited';
    try{ pendingPermissions=JSON.parse(json||'{}'); }catch(e){ pendingPermissions={}; }
    if(targetAccessLevel==='full'){
      pendingPermissions={};
      modules.forEach(([key])=>pendingPermissions[key]={permission:'write'});
    }
    renderRows();
    document.getElementById('accessPermissionsOverlay').style.display='flex';
  };
  window.closeAccessPermissions=function(){document.getElementById('accessPermissionsOverlay').style.display='none';};
  window.saveAccessPermissions=function(){
    if(targetAccessLevel!=='full'){
      document.querySelectorAll('#alpModuleRows select').forEach(s=>{
        if(s.value==='none') delete pendingPermissions[s.dataset.module];
        else pendingPermissions[s.dataset.module]={permission:targetAccessLevel==='read_only'?'read':s.value};
      });
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
      const all={}; modules.forEach(([key])=>all[key]={permission:'write',can_approve_enrollment:false});
      hidden.value=JSON.stringify(all);
      return;
    }
    openAccessPermissions(formId,level,hidden.value);
  };
  // Show the "Full Access Duration (days)" field only for a non-Principal admin set to Full.
  window.syncFullAccessDays=function(formId){
    const form=document.getElementById(formId);
    const row=document.getElementById(formId==='aumForm'?'aumFullDaysRow':'editFullDaysRow');
    const input=form&&form.querySelector('[name="full_access_days"]');
    if(!form||!row||!input) return;
    const role=form.querySelector('[name="role"]')?.value;
    const level=form.querySelector('[name="access_level"]')?.value;
    const pos=form.querySelector('[name="position"]')?.value;
    const show=role==='admin'&&level==='full'&&pos!=='principal';
    const canKeep=form.dataset.canKeepExpiry==='1';
    row.style.display=show?'':'none';
    input.disabled=!show;
    input.required=show&&!canKeep;
    const keep=row.querySelector('[data-keep-hint]');
    if(keep) keep.style.display=(show&&canKeep)?'':'none';
  };
  document.addEventListener('DOMContentLoaded',()=>{
    [['aumForm'],['editUserForm']].forEach(([id])=>{
      const f=document.getElementById(id); if(!f) return;
      f.querySelector('[name="access_level"]')?.addEventListener('change',()=>syncFullAccessDays(id));
      f.querySelector('[name="position"]')?.addEventListener('change',()=>syncFullAccessDays(id));
    });
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
