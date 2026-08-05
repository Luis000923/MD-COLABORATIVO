(function () {
  if (typeof mermaid === 'undefined') return;

  var blocks = document.querySelectorAll('code.language-mermaid');
  if (!blocks.length) return;

  // Los diagramas heredan la paleta de la app en lugar del morado por
  // defecto de mermaid, así el tema claro y el oscuro salen coherentes.
  var estilos = getComputedStyle(document.documentElement);
  function token(nombre, respaldo) {
    var valor = (estilos.getPropertyValue(nombre) || '').trim();
    return valor || respaldo;
  }

  mermaid.initialize({
    startOnLoad: false,
    theme: 'base',
    securityLevel: 'strict',
    fontFamily: 'inherit',
    themeVariables: {
      background: token('--panel-raised', '#1e1e1b'),
      mainBkg: token('--accent-soft', '#2b241a'),
      primaryColor: token('--accent-soft', '#2b241a'),
      primaryTextColor: token('--text', '#ececea'),
      primaryBorderColor: token('--accent', '#c98a4b'),
      secondaryColor: token('--tag-neutral', '#2a2a26'),
      tertiaryColor: token('--bg-sunken', '#0d0d0d'),
      nodeBorder: token('--accent', '#c98a4b'),
      lineColor: token('--text-dim', '#a8a69b'),
      textColor: token('--text', '#ececea'),
      clusterBkg: token('--bg-sunken', '#0d0d0d'),
      clusterBorder: token('--border', '#2f2f2a'),
      edgeLabelBackground: token('--panel-raised', '#1e1e1b'),
      fontSize: '15px'
    }
  });

  blocks.forEach(function (block) {
    var pre = block.closest('pre');
    if (!pre) return;

    var container = document.createElement('div');
    container.className = 'mermaid';
    container.textContent = block.textContent;
    // Guardar el código fuente: si el diagrama no compila, lo mostramos tal
    // cual en lugar de dejar un hueco o el mensaje de error crudo de mermaid.
    container.dataset.fuente = block.textContent;
    pre.replaceWith(container);
  });

  Promise.resolve(mermaid.run({ querySelector: '.mermaid', suppressErrors: true }))
    .catch(function () { /* el fallback por nodo se encarga */ })
    .finally(function () {
      document.querySelectorAll('.mermaid').forEach(function (nodo) {
        if (nodo.querySelector('svg')) return;

        var aviso = document.createElement('p');
        aviso.className = 'mermaid-error';
        aviso.textContent = 'No se pudo dibujar este diagrama. Se muestra el código original.';

        var codigo = document.createElement('pre');
        codigo.textContent = nodo.dataset.fuente || nodo.textContent;

        nodo.replaceWith(aviso, codigo);
      });
    });
})();
