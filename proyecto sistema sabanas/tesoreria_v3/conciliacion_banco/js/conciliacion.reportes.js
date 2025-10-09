(() => {
  const store = window.ConcStore;
  if (!store) return;

  const btnDescargar = document.getElementById('btn-descargar');
  if (!btnDescargar) return;

  btnDescargar.addEventListener('click', () => {
    const conc = store.state.conciliacion;
    if (!conc) {
      alert('Seleccioná una conciliación primero.');
      return;
    }
    const url = `reportes_api.php?id_conciliacion=${conc.id_conciliacion}&formato=csv`;
    window.open(url, '_blank');
  });
})();
