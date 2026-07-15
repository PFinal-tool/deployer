function confirmDelete(msg){return confirm(msg||'确定删除?');}
function toggleLog(id){
  var row=document.getElementById('log-row-'+id);
  if(!row)return;
  var show=row.style.display==='none';
  row.style.display=show?'table-row':'none';
  if(show){
    var el=document.getElementById('log-'+id);
    if(el&&el.getAttribute('data-loaded')!=='1'){
      el.textContent='加载中...';
      fetch('?action=api&endpoint=deploy_log&deployment_id='+id)
        .then(function(r){return r.json();})
        .then(function(d){el.textContent=d.output||d.error||'暂无日志';el.setAttribute('data-loaded','1');})
        .catch(function(e){el.textContent='加载失败: '+e.message;});
    }
  }
}
