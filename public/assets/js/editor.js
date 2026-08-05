(function () {
  var textarea = document.getElementById('editor');
  if (!textarea) return;

  var archivoId = textarea.dataset.archivoId;
  var baseVersion = textarea.dataset.baseVersion || '0';
  var csrfToken = textarea.dataset.csrf || '';

  var estadoEl = document.getElementById('guardar-estado');

  function avisar(texto, clase) {
    if (!estadoEl) return;
    estadoEl.textContent = texto;
    estadoEl.className = 'guardar-estado' + (clase ? ' ' + clase : '');
  }

  // Si el CDN de EasyMDE no carga, el <textarea> plano sigue siendo editable
  // y Guardar sigue funcionando: solo se pierden toolbar y snippets.
  var easymde = null;
  if (typeof EasyMDE !== 'undefined') {
    easymde = new EasyMDE({
      element: textarea,
      spellChecker: false,
      autofocus: true,
      status: ['lines', 'words', 'cursor'],
      toolbar: [
        'bold', 'italic', 'strikethrough', 'heading', '|',
        'quote', 'unordered-list', 'ordered-list', '|',
        'link', 'image', 'code', '|',
        'preview', 'side-by-side', 'fullscreen', '|',
        'guide'
      ]
    });
  }

  var cm = easymde ? easymde.codemirror : null;

  function contenidoActual() {
    return cm ? cm.getValue() : textarea.value;
  }

  // ---- Autor: recordar en localStorage ----
  var autorInput = document.getElementById('autor-nombre');
  if (autorInput) {
    var savedAutor = localStorage.getItem('md_autor_nombre');
    if (savedAutor) autorInput.value = savedAutor;
    autorInput.addEventListener('change', function () {
      localStorage.setItem('md_autor_nombre', autorInput.value);
    });
  }

  var SNIPPETS = {
    'mermaid': '```mermaid\nflowchart TD\n    A[Inicio] --> B{Decisión}\n    B -->|Sí| C[Fin]\n    B -->|No| D[Fin alterno]\n```\n',
    'tabla': '| Columna 1 | Columna 2 | Columna 3 |\n| --- | --- | --- |\n| dato | dato | dato |\n',
    'code': '```\n\n```',
    'link': '[texto](url)',
    'img': '![alt](url)'
  };

  function currentWord(editor) {
    var cursor = editor.getCursor();
    var line = editor.getLine(cursor.line);
    var start = cursor.ch;
    var end = cursor.ch;
    while (start > 0 && /[a-zA-Z]/.test(line.charAt(start - 1))) start--;
    while (end < line.length && /[a-zA-Z]/.test(line.charAt(end))) end++;
    return { text: line.slice(start, end), from: { line: cursor.line, ch: start }, to: { line: cursor.line, ch: end } };
  }

  // Inserta texto en la posición del cursor, con o sin CodeMirror montado.
  function insertarTexto(texto) {
    if (cm) {
      cm.replaceRange(texto, cm.getCursor());
      cm.focus();
      return;
    }
    var inicio = textarea.selectionStart || 0;
    var fin = textarea.selectionEnd || inicio;
    textarea.value = textarea.value.slice(0, inicio) + texto + textarea.value.slice(fin);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = inicio + texto.length;
  }

  if (cm) {
  // Popup de sugerencias propio (sin depender del addon show-hint de
  // CodeMirror, que requiere un CodeMirror global — EasyMDE bundlea el
  // suyo internamente y no lo expone en window).
  var hintBox = document.createElement('div');
  hintBox.className = 'snippet-hint hidden';
  document.body.appendChild(hintBox);

  function ocultarHint() {
    hintBox.classList.add('hidden');
  }

  cm.on('inputRead', function (editor, change) {
    if (change.origin !== '+input') return;

    var word = currentWord(editor);
    if (word.text.length < 3) {
      ocultarHint();
      return;
    }

    var matches = Object.keys(SNIPPETS).filter(function (key) {
      return key.indexOf(word.text.toLowerCase()) === 0;
    });
    if (!matches.length) {
      ocultarHint();
      return;
    }

    hintBox.innerHTML = matches.map(function (key) {
      return '<div class="snippet-hint-item" data-key="' + key + '">' + key + ' → snippet <span class="hint-key">(Tab)</span></div>';
    }).join('');

    var coords = editor.cursorCoords();
    hintBox.style.left = coords.left + 'px';
    hintBox.style.top = coords.bottom + 4 + 'px';
    hintBox.classList.remove('hidden');
  });

  cm.on('blur', function () {
    setTimeout(ocultarHint, 150);
  });
  cm.on('keydown', function (editor, e) {
    if (e.key === 'Escape') ocultarHint();
  });

  hintBox.addEventListener('mousedown', function (e) {
    var item = e.target.closest('.snippet-hint-item');
    if (!item) return;
    e.preventDefault();
    var word = currentWord(cm);
    var key = item.dataset.key;
    cm.replaceRange(SNIPPETS[key], word.from, word.to);
    ocultarHint();
    cm.focus();
  });

  // Cierre automático de fences ``` al escribir el tercer backtick
  cm.on('inputRead', function (editor, change) {
    if (change.text.join('') !== '`') return;
    var cursor = editor.getCursor();
    var line = editor.getLine(cursor.line);
    var beforeCursor = line.slice(0, cursor.ch);
    if (beforeCursor === '```') {
      var cursorPos = editor.getCursor();
      editor.replaceRange('\n\n```', cursorPos);
      editor.setCursor({ line: cursorPos.line + 1, ch: 0 });
    }
  });

  // Autocompletado real de snippets: interceptar Tab cuando el texto antes
  // del cursor coincide exactamente con una clave de SNIPPETS
  cm.setOption('extraKeys', Object.assign({}, cm.getOption('extraKeys'), {
    Tab: function (editor) {
      var word = currentWord(editor);
      if (SNIPPETS[word.text.toLowerCase()]) {
        editor.replaceRange(SNIPPETS[word.text.toLowerCase()], word.from, word.to);
        return;
      }
      editor.replaceSelection('    ');
    }
  }));
  }

  // ---- Insertar diagrama Mermaid ----
  document.getElementById('btn-insertar-mermaid').addEventListener('click', function () {
    insertarTexto(SNIPPETS.mermaid);
  });

  // ---- Insertar esquema (outline) a partir de encabezados existentes ----
  document.getElementById('btn-insertar-outline').addEventListener('click', function () {
    var contenido = contenidoActual();
    var lineas = contenido.split(/\r?\n/);
    var encabezados = [];

    lineas.forEach(function (linea) {
      var m = linea.match(/^(#{1,6})\s+(.+?)\s*#*$/);
      if (m) {
        encabezados.push({ nivel: m[1].length, texto: m[2].trim() });
      }
    });

    var outline;
    if (encabezados.length) {
      outline = encabezados.map(function (h) {
        var indent = '  '.repeat(h.nivel - 1);
        var slug = h.texto.toLowerCase().replace(/[^\w\s-]/g, '').trim().replace(/\s+/g, '-');
        return indent + '- [' + h.texto + '](#' + slug + ')';
      }).join('\n');
    } else {
      outline = '- Tema principal\n  - Subtema 1\n  - Subtema 2\n    - Detalle\n- Otro tema principal\n';
    }

    insertarTexto('\n## Esquema\n\n' + outline + '\n');
  });

  // ---- Modal: insertar tabla ----
  var modal = document.getElementById('modal-tabla');

  function abrirModal() {
    modal.classList.remove('hidden');
    var primer = document.getElementById('tabla-filas');
    if (primer) primer.focus();
  }

  function cerrarModal() {
    modal.classList.add('hidden');
    if (cm) cm.focus();
  }

  document.getElementById('btn-insertar-tabla').addEventListener('click', abrirModal);
  document.getElementById('tabla-cancelar').addEventListener('click', cerrarModal);

  // Cerrar con Escape o clic en el fondo, como cualquier diálogo.
  modal.addEventListener('click', function (e) {
    if (e.target === modal) cerrarModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) cerrarModal();
  });

  document.getElementById('tabla-confirmar').addEventListener('click', function () {
    var filas = parseInt(document.getElementById('tabla-filas').value, 10) || 1;
    var columnas = parseInt(document.getElementById('tabla-columnas').value, 10) || 1;

    var header = '| ' + Array.from({ length: columnas }, function (_, i) { return 'Columna ' + (i + 1); }).join(' | ') + ' |';
    var separator = '| ' + Array(columnas).fill('---').join(' | ') + ' |';
    var rows = [];
    for (var r = 0; r < filas; r++) {
      rows.push('| ' + Array(columnas).fill('   ').join(' | ') + ' |');
    }

    var tablaMd = '\n' + [header, separator].concat(rows).join('\n') + '\n';

    insertarTexto(tablaMd);
    cerrarModal();
  });

  // ---- Guardar ----
  var btnGuardar = document.getElementById('btn-guardar');
  var contenidoGuardado = contenidoActual();
  var guardando = false;

  function hayCambios() {
    return contenidoActual() !== contenidoGuardado;
  }

  function guardar() {
    if (guardando) return;

    var autorNombre = autorInput ? autorInput.value.trim() : '';
    if (!autorNombre) {
      avisar('Indica tu nombre antes de guardar.', 'error');
      if (autorInput) autorInput.focus();
      return;
    }

    var contenido = contenidoActual();

    guardando = true;
    btnGuardar.disabled = true;
    avisar('Guardando…');

    fetch('save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        archivo_id: archivoId,
        autor_nombre: autorNombre,
        contenido: contenido,
        base_version: baseVersion,
        csrf_token: csrfToken
      })
    })
      .then(function (res) {
        // save.php siempre responde JSON, pero un error 500 del servidor o una
        // página de mantenimiento devolverían HTML: no reventar el parseo.
        return res.text().then(function (texto) {
          var data;
          try {
            data = JSON.parse(texto);
          } catch (e) {
            data = { ok: false, error: 'Respuesta inesperada del servidor (' + res.status + ')' };
          }
          return { status: res.status, data: data };
        });
      })
      .then(function (r) {
        var data = r.data;
        if (data.ok) {
          contenidoGuardado = contenido;
          avisar('Guardado como v' + data.numero_version + '.', 'ok');
          setTimeout(function () {
            window.location.href = 'view.php?id=' + archivoId;
          }, 700);
          return;
        }

        if (r.status === 409 && data.conflicto) {
          // Otra persona guardó primero: no pisamos su trabajo (S4).
          avisar(data.error, 'error');
          if (confirm(data.error + '\n\n¿Abrir la versión actual en otra pestaña para comparar? (Tu texto se conserva aquí.)')) {
            window.open('view.php?id=' + archivoId, '_blank');
          }
        } else {
          avisar('Error: ' + (data.error || 'desconocido'), 'error');
        }
      })
      .catch(function () {
        avisar('Error de red al guardar.', 'error');
      })
      .finally(function () {
        guardando = false;
        btnGuardar.disabled = false;
      });
  }

  btnGuardar.addEventListener('click', guardar);

  // Ctrl/Cmd+S guarda en lugar de abrir el diálogo del navegador.
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      guardar();
    }
  });

  // Avisar antes de perder cambios sin guardar.
  window.addEventListener('beforeunload', function (e) {
    if (!hayCambios()) return;
    e.preventDefault();
    e.returnValue = '';
  });

  if (!easymde) {
    avisar('Editor enriquecido no disponible: se está usando el editor de texto simple.', 'error');
  }
})();
