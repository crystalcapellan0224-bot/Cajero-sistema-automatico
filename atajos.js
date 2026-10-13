const campoMonto = document.getElementById('monto');

document.querySelectorAll('.atajo').forEach((boton) => {
  boton.addEventListener('click', () => {
    campoMonto.value = boton.dataset.monto;
    campoMonto.focus();
  });
});
