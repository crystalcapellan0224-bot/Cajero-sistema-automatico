(function () {
  const boton = document.getElementById('chat-boton');
  const panel = document.getElementById('chat-panel');
  const cerrar = document.getElementById('chat-cerrar');
  const cuerpo = document.getElementById('chat-panel-cuerpo');
  const pista = document.querySelector('.chat-pista');

  if (!boton || !panel) {
    return;
  }

  const alAbrir = () => {
    panel.hidden = false;
    boton.classList.add('oculto');
    cuerpo.scrollTop = cuerpo.scrollHeight;
  };

  const alCerrar = () => {
    panel.hidden = true;
    boton.classList.remove('oculto');
  };

  boton.addEventListener('click', alAbrir);
  cerrar.addEventListener('click', alCerrar);

  document.addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape' && !panel.hidden) {
      alCerrar();
    }
  });

  document.querySelectorAll('[data-pregunta]').forEach((chip) => {
    chip.addEventListener('click', () => {
      const campo = panel.querySelector('input[name="pregunta"]');

      if (!campo) {
        return;
      }

      campo.value = chip.dataset.pregunta;
      campo.form.submit();
    });
  });

  if (pista && document.querySelector('.alerta')) {
    pista.textContent = 'Chatea con el asistente';
  }
})();