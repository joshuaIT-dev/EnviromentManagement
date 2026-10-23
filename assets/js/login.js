(() => {
  const choice = document.querySelector('#role-select');
  const label = document.querySelector('#account-id-label');
  const recovery = document.querySelector('#faculty-recovery');
  function updateRole() {
    const role = choice.value;
    label.textContent = role === 'admin' ? 'Admin ID' : 'Faculty ID';
    recovery.hidden = role !== 'faculty';
  }
  choice.addEventListener('change', updateRole);
  updateRole();
})();
