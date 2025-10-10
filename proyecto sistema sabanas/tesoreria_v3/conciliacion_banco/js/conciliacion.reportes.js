(() => {
  const store = window.ConcStore;
  if (!store) return;

  const btnDescargar = document.getElementById('btn-descargar');
  if (!btnDescargar) return;

  btnDescargar.addEventListener('click', () => {
    const id = btnDescargar.dataset.idConciliacion || (store.state.conciliacion && store.state.conciliacion.id_conciliacion);
    if (!id) {
      alert('Seleccione una conciliacion primero.');
      return;
    }
    const url = `reportes_api.php?id_conciliacion=${id}&formato=csv`;
    window.open(url, '_blank');
  });
})();
