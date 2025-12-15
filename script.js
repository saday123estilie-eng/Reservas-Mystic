// ---------- Utilities ----------
const $ = (s, sc) => (sc || document).querySelector(s);
const $$ = (s, sc) => Array.from((sc || document).querySelectorAll(s));

// Función para convertir valores en formato miles (sin decimales)
const toInt = v => Number(String(v || '').replace(/[^\d-]/g, '')) || 0;
const miles = n => new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(Math.max(0, Math.round(n || 0)));

// Función para formatear inputs de dinero
function attachMiles() {
  $$('input[data-format-miles]').forEach(el => {
    el.addEventListener('focus', () => { el.value = String(toInt(el.value)); });
    el.addEventListener('input', () => { el.value = miles(toInt(el.value)); renderResumen(); recalcSaldo(); });
    el.addEventListener('blur', () => { el.value = miles(toInt(el.value)); renderResumen(); recalcSaldo(); });
  });
}
attachMiles();

// Función para calcular la edad desde el cumpleaños
function calcEdad() {
  const v = $('#cumple').value;
  if (!v) { $('#edad').value = ''; return; }
  const b = new Date(v), n = new Date();
  let e = n.getFullYear() - b.getFullYear();
  const m = n.getMonth() - b.getMonth();
  if (m < 0 || (m === 0 && n.getDate() < b.getDate())) e--;
  $('#edad').value = e > 0 ? e : 0;
}
$('#cumple').addEventListener('input', calcEdad);

// Generar código automáticamente si no está presente
function genCodigo() {
  const d = new Date();
  return 'MP-' + d.toISOString().slice(2, 10).replace(/-/g, '') + '-' + Math.random().toString(36).slice(2, 6).toUpperCase();
}
(function ensureCodigo() {
  if (!$('#codigo').value.trim()) $('#codigo').value = genCodigo();
})();

// Fecha mínima hoy
(function () {
  const t = new Date();
  const y = t.getFullYear(), m = String(t.getMonth() + 1).padStart(2, '0'), d = String(t.getDate()).padStart(2, '0');
  $('#fecha_ingreso').min = `${y}-${m}-${d}`;
})();

// ---------- Cálculo del saldo ----------
function recalcSaldo() {
  const pt = toInt($('#pago_total').value); // Obtener el pago total
  const sum = toInt($('#pago_datafono').value) + toInt($('#pago_transferencia').value) + toInt($('#pago_efectivo').value);
  const s = Math.max(0, pt - sum);

  // Actualizar el campo de saldo en la interfaz
  $('#saldo').value = miles(s);
  $('#r_saldo').textContent = 'COP ' + miles(s);
  $('#r_saldo_m').textContent = 'COP ' + miles(s);

  // Guardar el saldo actualizado en localStorage
  localStorage.setItem('saldo', s);

  // Actualizar el valor de pago total en el formulario
  $('#pago_total').value = miles(s); // Actualizamos el campo de pago total
}
['pago_total', 'pago_datafono', 'pago_transferencia', 'pago_efectivo'].forEach(id => {
  document.getElementById(id).addEventListener('input', () => { recalcSaldo(); renderResumen(); });
});
recalcSaldo();

// ---------- Mantener saldo al refrescar la página ----------
window.addEventListener('load', () => {
  const storedSaldo = localStorage.getItem('saldo');
  if (storedSaldo) {
    // Establecer el saldo almacenado
    $('#saldo').value = miles(storedSaldo); 
    $('#r_saldo').textContent = 'COP ' + miles(storedSaldo); // Mostrar el saldo en el resumen
    $('#r_saldo_m').textContent = 'COP ' + miles(storedSaldo); // Mostrar el saldo en el resumen móvil
  }
});

// ---------- Limpiar formulario ----------
$('#btnLimpiar').addEventListener('click', () => {
  document.getElementById('formReserva').reset();
  // Regenerar código y reset dinero
  $('#codigo').value = genCodigo();
  attachMiles(); recalcSaldo(); renderResumen();
  // Colapsar selección de servicios
  $$('#svc_container .svc-check').forEach(ch => { ch.checked = false; ch.closest('.svc-row').querySelector('.qty').value = ''; ch.closest('.svc-row').querySelector('.qty').disabled = true; });
  updateSvcCount();
});

// ---------- Validación mínima al enviar ----------
document.getElementById('formReserva').addEventListener('submit', (e) => {
  const req = ['nombre', 'cedula', 'whatsapp', 'fecha_ingreso', 'noches', 'adultos'];
  let ok = true, first = null;
  req.forEach(id => {
    const el = document.getElementById(id);
    const valid = !!el.value && (el.checkValidity ? el.checkValidity() : true);
    el.style.boxShadow = valid ? '' : '0 0 0 3px rgba(244,63,94,.25)';
    el.style.borderColor = valid ? '' : 'rgba(244,63,94,.6)';
    el.setAttribute('aria-invalid', valid ? 'false' : 'true');
    if (!valid && !first) first = el, ok = false;
  });
  if (!ok) { e.preventDefault(); first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus(); }
});

// ---------- Servicios (data + render) ----------
const svcData = {
  'Otros servicios': ['Chelas', 'Cabalgata', 'Helicóptero', 'Tatto', 'Pesca D.', 'Planta', 'Museo', 'Comfiand.', 'Yoga'],
  'Náuticos': ['Paddle', 'Lancha', 'Banana', 'Ponto', 'Jetsky', 'Kitesurf'],
  'Decoraciones': ['D. Román', 'D. Cumple', 'D. Comple.', 'Picnic R.', 'Frase', 'Globos C.'],
  'Alimentación': ['Desayuno', 'Almuerzo', 'Cena', 'Room Servic', 'Refrigerios', 'Bebida'],
  'Experienciales': ['Cine', 'Kit fogata', 'Masajes', 'Cuatrimoto', 'Micheladas', 'PaintBall'],
  'Hospedaje': ['Camp 4', 'Habit', 'Glamp', 'Camp. 6', 'Camp. 8', 'Pasadía']
};

function renderSvc() {
  const wrap = $('#svc_container');
  wrap.innerHTML = '';
  const gtpl = $('#svc_group_tpl').innerHTML;
  const rtpl = $('#svc_row_tpl').innerHTML;
  Object.entries(svcData).forEach(([title, rows]) => {
    let rowsHtml = rows.map(name => rtpl.replace('__NAME__', name)).join('');
    const html = gtpl.replace('__TITLE__', title).replace('__ROWS__', rowsHtml);
    const frag = document.createElement('div');
    frag.innerHTML = html;
    wrap.appendChild(frag.firstElementChild);
  });

  // Behaviors per row
  $$('#svc_container .svc-row').forEach(row => {
    const check = $('.svc-check', row); const qty = $('.qty', row);
    check.addEventListener('change', () => { qty.disabled = !check.checked; if (!check.checked) { qty.value = ''; } updateSvcCount(); });
    qty.addEventListener('input', () => { qty.value = miles(toInt(qty.value)); });
  });
  attachMiles();
  updateSvcCount();
}
renderSvc();

function updateSvcCount() {
  const n = $$('#svc_container .svc-check:checked').length;
  $('#svc_count').textContent = n;
  // update group counters
  $$('#svc_container details').forEach(d => {
    const nIn = $$('.svc-check:checked', d).length; $('.svc-in', d).textContent = nIn;
  });
}

// Toolbar actions
$('#svc_expand').addEventListener('click', () => $$('#svc_container details').forEach(d => d.open = true));
$('#svc_collapse').addEventListener('click', () => $$('#svc_container details').forEach(d => d.open = false));
$('#svc_clear').addEventListener('click', () => {
  $$('#svc_container .svc-check').forEach(ch => { ch.checked = false; const q = ch.closest('.svc-row').querySelector('.qty'); q.value = ''; q.disabled = true; });
  updateSvcCount();
});

// Búsqueda servicios
$('#svc_search').addEventListener('input', (e) => {
  const q = e.target.value.trim().toLowerCase();
  $$('#svc_container .svc-row').forEach(row => {
    const name = $('.svc-name', row).textContent.toLowerCase();
    row.style.display = name.includes(q) ? '' : 'none';
  });
});

// ---------- Resumen en vivo ----------
const R = {
  cliente: $('#r_cliente'),
  contacto: $('#r_contacto'),
  plan: $('#r_plan'),
  ingreso: $('#r_ingreso'),
  noches: $('#r_noches'),
  personas: $('#r_personas'),
  valor: $('#r_valor'),
  saldo: $('#r_saldo'), // Añadir saldo en resumen
};

function renderResumen() {
  const nombre = $('#nombre').value || '—';
  const cedula = $('#cedula').value || '—';
  const tel = $('#whatsapp').value || '—';
  const plan = $('#plan').value || '—';
  const fecha = $('#fecha_ingreso').value || '—';
  const noches = $('#noches').value || '—';
  const adultos = toInt($('#adultos').value); 
  const menores = toInt($('#menores').value);
  const personas = (adultos || 0) + (menores || 0);
  const valor = 'COP ' + miles(toInt($('#valor_total').value));
  const saldo = toInt($('#saldo').value);  // Obtener saldo actualizado

  // Actualizar el resumen con los valores de la reserva
  R.cliente.textContent = nombre + ' · CC ' + cedula;
  R.contacto.textContent = tel;
  R.plan.textContent = plan;
  R.ingreso.textContent = fecha;
  R.noches.textContent = noches;
  R.personas.textContent = personas > 0 ? personas : '—';
  R.valor.textContent = valor;

  // Actualizar el saldo en el resumen
  R.saldo.textContent = 'COP ' + miles(saldo); // Actualizar el saldo en el resumen
}

['nombre', 'cedula', 'whatsapp', 'plan', 'fecha_ingreso', 'noches', 'adultos', 'menores', 'valor_total', 'saldo'].forEach(id => {
  document.getElementById(id).addEventListener('input', renderResumen);
});
renderResumen();

// ---------- Función para registrar pagos ----------
function registrarPago(pagoId, pagoButtonId) {
  const pagoValor = toInt($(`#${pagoId}`).value); // Obtener el valor del pago
  const saldoActual = toInt($('#saldo').value.replace(/[^\d-]/g, '')); // Obtener el saldo actual
  
  if (pagoValor > 0) {
    // Verificar si ya se registró el pago
    if ($(`#${pagoButtonId}`).disabled) {
      alert("Este pago ya ha sido registrado.");
      return; // No hacer nada si el pago ya ha sido registrado
    }

    // Restamos el valor del pago al saldo actual
    const nuevoSaldo = Math.max(0, saldoActual - pagoValor);

    // Actualizar el campo de saldo en la interfaz
    $('#saldo').value = miles(nuevoSaldo);
    $('#r_saldo').textContent = 'COP ' + miles(nuevoSaldo);
    $('#r_saldo_m').textContent = 'COP ' + miles(nuevoSaldo);

    // Guardar el saldo actualizado en localStorage
    localStorage.setItem('saldo', nuevoSaldo);

    // Deshabilitar el botón después de registrar el pago
    $(`#${pagoButtonId}`).disabled = true;

    // Actualizar el valor de pago total también
    $('#pago_total').value = miles(nuevoSaldo); // Actualizamos el campo de pago total

    // Opcional: Puedes agregar un mensaje de confirmación
    alert(`Pago registrado: COP ${miles(pagoValor)}. Nuevo saldo: COP ${miles(nuevoSaldo)}`);
  } else {
    alert("Por favor ingrese un valor válido para el pago.");
  }
}

// ---------- Función para corregir el pago ----------
function corregirPago(pagoId, pagoButtonId) {
  const pagoValor = toInt($(`#${pagoId}`).value); // Obtener el valor del pago que se quiere corregir
  const saldoActual = toInt($('#saldo').value.replace(/[^\d-]/g, '')); // Obtener el saldo actual
  const pagoTotal = toInt($('#pago_total').value.replace(/[^\d-]/g, '')); // Obtener el valor total pagado
  
  if (pagoValor > 0) {
    // Obtener el valor previamente registrado (el que se corrigió)
    const pagoRegistrado = toInt($(`#${pagoId}`).dataset.originalValue || 0);

    // Sumamos el valor corregido al saldo
    const nuevoSaldo = saldoActual + pagoRegistrado;

    // Actualizamos el saldo en la interfaz
    $('#saldo').value = miles(nuevoSaldo);
    $('#r_saldo').textContent = 'COP ' + miles(nuevoSaldo);
    $('#r_saldo_m').textContent = 'COP ' + miles(nuevoSaldo);

    // Actualizar el valor de pago total también
    const nuevoPagoTotal = nuevoSaldo + pagoValor;
    $('#pago_total').value = miles(nuevoPagoTotal);

    // Limpiar el campo de pago corregido y restablecer el valor original registrado
    $(`#${pagoId}`).value = '';
    $(`#${pagoId}`).dataset.originalValue = ''; 

    // Rehabilitar el botón de pago para permitir registrar un nuevo pago
    $(`#${pagoButtonId}`).disabled = false;

    alert("Pago corregido correctamente.");
  } else {
    alert("Por favor ingrese un valor válido para corregir el pago.");
  }
}

// Asignar la función de registrar pago a los botones de pago
document.getElementById('pago1_button').addEventListener('click', () => registrarPago('pago1_valor', 'pago1_button'));
document.getElementById('pago2_button').addEventListener('click', () => registrarPago('pago2_valor', 'pago2_button'));
document.getElementById('pago3_button').addEventListener('click', () => registrarPago('pago3_valor', 'pago3_button'));

// Asignar la función de corregir pago a los botones de corregir
document.getElementById('corregir1_button').addEventListener('click', () => corregirPago('pago1_valor', 'pago1_button'));
document.getElementById('corregir2_button').addEventListener('click', () => corregirPago('pago2_valor', 'pago2_button'));
document.getElementById('corregir3_button').addEventListener('click', () => corregirPago('pago3_valor', 'pago3_button'));
