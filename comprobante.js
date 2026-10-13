(function () {
  const boton = document.getElementById('btn-imprimir');

  if (!boton) {
    return;
  }

  boton.addEventListener('click', () => window.print());

  if (!document.getElementById('comprobante')) {
    return;
  }

  if (window.matchMedia('print').matches) {
    boton.disabled = true;
  }
})();