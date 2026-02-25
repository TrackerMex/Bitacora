const USER_ROLES = {
  editor: {
    username: "user",
    password: "editor",
    tabs: [0, 1, 2, 3, 4, 5, 6, 7],
  },
  lector: { username: "view", password: "read", tabs: [3, 4, 5, 6, 7] },
};

let userRole = null;
let userUnidades = [];
let userTabs = [];
let username = "";

function setUserRole(role, unidades = [], tabs = []) {
  userRole = role;
  userUnidades = unidades;
  userTabs = tabs;
  try {
    sessionStorage.setItem("bitacoraUserRole", role);
    sessionStorage.setItem("bitacoraUserUnidades", JSON.stringify(unidades));
    sessionStorage.setItem("bitacoraUserTabs", JSON.stringify(tabs));
    sessionStorage.setItem("bitacoraUsername", username);
  } catch (e) {}
  const loginModal = document.getElementById("login-modal");
  if (loginModal) loginModal.classList.add("hidden");

  const userInfo = document.getElementById("user-info");
  const usernameDisplay = document.getElementById("username-display");
  if (userInfo && usernameDisplay) {
    usernameDisplay.textContent = username;
    userInfo.classList.remove("hidden");
  }

  updateTabVisibility();
}

function handleLogout() {
  userRole = null;
  userUnidades = [];
  userTabs = [];
  username = "";

  try {
    sessionStorage.removeItem("bitacoraUserRole");
    sessionStorage.removeItem("bitacoraUserUnidades");
    sessionStorage.removeItem("bitacoraUserTabs");
    sessionStorage.removeItem("bitacoraUsername");
  } catch (e) {}

  const userInfo = document.getElementById("user-info");
  if (userInfo) userInfo.classList.add("hidden");

  filteredDespachosData = [];
  renderAllTabs();

  const loginModal = document.getElementById("login-modal");
  if (loginModal) loginModal.classList.remove("hidden");
}

Chart.register(ChartDataLabels);

let allDespachosData = [];
let filteredDespachosData = [];
let charts = {};
let lastInformeData = [];

function updateClienteDisplay() {
  const nameEl = document.getElementById("cliente-display-name");
  if (!nameEl) return;

  const clientes = [
    ...new Set(
      (filteredDespachosData || [])
        .map((d) => String(d?.cliente || "").trim())
        .filter(Boolean),
    ),
  ];

  if (!clientes.length) {
    nameEl.textContent = "-";
    return;
  }

  if (clientes.length === 1) {
    nameEl.textContent = clientes[0];
    return;
  }

  nameEl.textContent = `${clientes.join(", ")}`;
}

async function enviarIncidencia(datosIncidencia) {
  const urlWebhook =
    "https://hook.us2.make.com/yndsuutplxn2j0n18o6l4u5qykhjrflj";
  try {
    const response = await fetch(urlWebhook, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        titulo: datosIncidencia.titulo,
        descripcion: datosIncidencia.cuerpo,
        fecha: new Date().toISOString(),
        direccion: datosIncidencia.direccion || "",
      }),
    });
    if (response.ok) console.log("Incidencia enviada con éxito");
    else console.warn("Make respondió con error HTTP:", response.status);
  } catch (error) {
    console.error("Error al conectar con Make:", error);
  }
}

function escapeHtml(v) {
  return String(v ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function handleIncidenciaTipoChange() {
  const sel = document.getElementById("incidencia-tipo");

  const wrap = document.getElementById("incidencia-direccion-wrapper");

  const input = document.getElementById("incidencia-direccion");

  if (!sel || !wrap || !input) return;

  const hasValue = String(sel.value || "").trim().length > 0;

  wrap.classList.toggle("hidden", !hasValue);

  if (!hasValue) {
    input.value = "";
  } else {
    input.focus();
  }
}

function excelDateToYYYYMMDD(value) {
  if (value === null || value === undefined) return "";
  if (value instanceof Date && !isNaN(value))
    return dayjs(value).format("YYYY-MM-DD");

  let s = String(value).trim();
  if (!s) return "";

  s = s.split(/\s+/)[0];
  s = s.replace(/\/+/g, "/").replace(/-+/g, "-");

  const parsed = dayjs(
    s,
    [
      "YYYY-MM-DD",
      "YYYY/MM/DD",
      "DD-MM-YYYY",
      "DD/MM/YYYY",
      "D-M-YYYY",
      "D/M/YYYY",
    ],
    true,
  );
  return parsed.isValid() ? parsed.format("YYYY-MM-DD") : "";
}

function parseExcelDateTime(timeValue, dateValue) {
  if (!timeValue) return "";

  const fullFormats = [
    "DD-MM-YYYY HH:mm:ss",
    "DD/MM/YYYY HH:mm:ss",
    "DD-MM-YYYY H:mm:ss",
    "DD/MM/YYYY H:mm:ss",
    "DD-MM-YYYY HH:mm",
    "DD/MM/YYYY HH:mm",
    "DD-MM-YYYY H:mm",
    "DD/MM/YYYY H:mm",
    "YYYY-MM-DD HH:mm:ss",
    "YYYY-MM-DD H:mm:ss",
    "YYYY/MM/DD HH:mm:ss",
    "YYYY/MM/DD H:mm:ss",
  ];

  let dt = dayjs(String(timeValue), fullFormats, true);

  if (!dt.isValid()) {
    const str = String(timeValue).trim();

    const dateTimeMatch = str.match(
      /^(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})\s+(\d{1,2}:\d{2}(?::\d{2})?)$/,
    );
    if (dateTimeMatch) {
      const datePart = dateTimeMatch[1];
      const timePart = dateTimeMatch[2];
      const baseParsed = dayjs(
        datePart,
        ["DD-MM-YYYY", "DD/MM/YYYY", "MM-DD-YYYY", "MM/DD/YYYY"],
        true,
      );
      const timeMatch = timePart.match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);

      if (baseParsed.isValid() && timeMatch) {
        dt = baseParsed
          .hour(parseInt(timeMatch[1], 10))
          .minute(parseInt(timeMatch[2], 10))
          .second(timeMatch[3] ? parseInt(timeMatch[3], 10) : 0);
      }
    }
  }

  if (!dt.isValid() && dateValue) {
    const base = dayjs(
      String(dateValue),
      ["DD-MM-YYYY", "DD/MM/YYYY", "YYYY-MM-DD"],
      true,
    );
    const m = String(timeValue).match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
    if (base.isValid() && m) {
      dt = base
        .hour(parseInt(m[1], 10))
        .minute(parseInt(m[2], 10))
        .second(m[3] ? parseInt(m[3], 10) : 0);
    }
  }

  return dt.isValid() ? dt.toISOString() : "";
}
function calcularEstatusAutomatico(data) {
  const hasSalidaUnidad = !!(
    data.realSalidaUnidad && String(data.realSalidaUnidad).trim()
  );
  const hasProcesoCarga = !!(data.realCarga && String(data.realCarga).trim());
  const hasSalidaCarga = !!(data.realSalida && String(data.realSalida).trim());
  const hasDescarga = !!(data.realDescarga && String(data.realDescarga).trim());

  const estatusManual = data.estatus_especial || "";
  if (
    estatusManual === "Cancelado" ||
    estatusManual === "Despacho No realizado"
  ) {
    return estatusManual;
  }

  if (hasDescarga) {
    return "Despacho realizado";
  }
  if (hasSalidaCarga && hasProcesoCarga && hasSalidaUnidad) {
    return "Salida de Carga";
  } else if (hasSalidaCarga) {
    return "Salida de Carga";
  } else if (hasProcesoCarga && hasSalidaUnidad) {
    return "Cargando";
  } else if (hasProcesoCarga) {
    return "Cargando";
  } else if (hasSalidaUnidad) {
    return "En ruta";
  } else {
    return "Programado";
  }
}
function calcularProgreso(data) {
  if (data.realDescarga && String(data.realDescarga).trim()) {
    return 100;
  }

  let completados = 0;
  if (data.realSalidaUnidad && String(data.realSalidaUnidad).trim())
    completados++;
  if (data.realCarga && String(data.realCarga).trim()) completados++;
  if (data.realSalida && String(data.realSalida).trim()) completados++;
  return Math.round((completados / 4) * 100);
}

function updateEstatusFromForm() {
  const form = document.getElementById("form-seguimiento");
  if (!form) return;
  const data = {
    realSalidaUnidad: form.elements["realSalidaUnidad"]?.value || "",
    realCarga: form.elements["realCarga"]?.value || "",
    realSalida: form.elements["realSalida"]?.value || "",
    realDescarga: form.elements["realDescarga"]?.value || "",
    estatus_especial: form.elements["estatus_especial"]?.value || "",
  };

  const estatusCalculado = calcularEstatusAutomatico(data);
  const progresoCalculado = calcularProgreso(data);

  const container = document.getElementById("estatus-badge-container");
  if (container) {
    container.innerHTML = renderEstatusBadge(estatusCalculado);
  }

  const progressFill = document.querySelector(".progress-fill");
  if (progressFill) {
    progressFill.style.width = progresoCalculado + "%";
  }

  const porcentajeEl = document.getElementById("progreso-porcentaje");
  if (porcentajeEl) {
    porcentajeEl.textContent = progresoCalculado + "%";
  }
}
function renderEstatusBadge(estatus) {
  const badges = {
    Programado:
      '<span class="badge-estatus badge-programado">⚙️ Programado</span>',
    "En ruta": '<span class="badge-estatus badge-en-ruta">🚛 En Ruta</span>',
    Cargando: '<span class="badge-estatus badge-cargando">📦 Cargando</span>',
    "Salida de Carga":
      '<span class="badge-estatus badge-salida-carga">🚚 Salida de Carga</span>',
    "Despacho realizado":
      '<span class="badge-estatus badge-realizado">✅ Despacho Realizado</span>',
    "Despacho No realizado":
      '<span class="badge-estatus badge-no-realizado">❌ No Entregado</span>',
    Cancelado:
      '<span class="badge-estatus badge-cancelado">🚫 Cancelado</span>',
  };
  return badges[estatus] || "";
}

function formatDateTime(iso) {
  if (!iso || !dayjs(iso).isValid()) return "-";
  const d = dayjs(iso);
  const date = d.format("DD/MM/YYYY");
  const hour = d.hour();
  const minute = d.format("mm");
  const second = d.format("ss");
  const hour12 = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
  const ampm = hour < 12 ? "a.m." : "p.m.";
  return `${date} ${String(hour12).padStart(2, "0")}:${minute}:${second} ${ampm}`;
}
function formatForInput(iso) {
  if (!iso) return "";
  const d = dayjs(iso);
  return d.isValid() ? d.format("YYYY-MM-DDTHH:mm:ss") : "";
}

function checkTimeDeviation(prog, real) {
  if (!prog || !real) return "task-cell";
  const p = dayjs(prog),
    r = dayjs(real);
  if (!p.isValid() || !r.isValid()) return "task-cell";
  const diff = r.diff(p, "minute");
  return diff > 10 ? "estatus-rojo" : "estatus-verde";
}

async function handleLogin(event) {
  if (event && event.preventDefault) event.preventDefault();
  const usernameInput = (
    document.getElementById("username")?.value || ""
  ).trim();
  const password = (document.getElementById("password")?.value || "").trim();
  const errorMessage = document.getElementById("login-error-message");
  if (errorMessage) errorMessage.classList.add("hidden");

  try {
    const response = await fetch("/bitacora_/src/usuarios/login.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        username: usernameInput,
        password: password,
      }),
    });

    const data = await response.json();

    if (!data.success) {
      if (errorMessage) {
        errorMessage.textContent =
          data.message || "Usuario o contraseña incorrectos.";
        errorMessage.classList.remove("hidden");
      }
      return false;
    }

    username = data.user.username;
    setUserRole(data.user.role, data.user.unidades, data.user.tabs);

    processData(allDespachosData);
  } catch (error) {
    console.error("Login error:", error);
    if (errorMessage) {
      errorMessage.textContent = "Error al iniciar sesión. Intenta de nuevo.";
      errorMessage.classList.remove("hidden");
    }
  }
  return false;
}

function updateTabVisibility() {
  const allowed = userTabs;
  for (let i = 0; i < 8; i++) {
    const btn = document.getElementById(`tab-btn-${i}`);
    const tab = document.getElementById(`tab-${i}`);
    if (btn) btn.classList.toggle("hidden", !allowed.includes(i));
    if (tab && !allowed.includes(i)) tab.classList.add("hidden");
  }
  changeTab(allowed[0] ?? 0);
}

function changeTab(index) {
  if (!userRole) {
    document.getElementById("login-modal").classList.remove("hidden");
    return;
  }
  const allowed = userTabs;
  if (!allowed.includes(index)) {
    alert("No tienes permiso para acceder a esta pestaña.");
    return;
  }
  for (let i = 0; i < 8; i++) {
    const btn = document.getElementById(`tab-btn-${i}`);
    const tab = document.getElementById(`tab-${i}`);
    if (btn && !btn.classList.contains("hidden")) {
      btn.classList.toggle("tab-active", i === index);
      btn.classList.toggle("text-gray-500", i !== index);
    }
    if (tab) tab.classList.toggle("hidden", i !== index);
  }
  if (index === 4) renderKPIs(filteredDespachosData);
  if (index === 5) {
    renderInforme(filteredDespachosData);
    renderIncidenciasExecutiveReport();
  }
  if (index === 6) cargarInformesGuardados();
  if (index === 7) renderValidacionesGPS();
}

function openModal(id) {
  document.getElementById(id).classList.remove("hidden");
}
function closeModal(id) {
  document.getElementById(id).classList.add("hidden");
}
window.onclick = (ev) => {
  if (ev.target.classList.contains("modal")) ev.target.classList.add("hidden");
};

async function loadDataFromGoogleSheets(isInitialLoad = true) {
  const url = "/bitacora_/src/api/google-sheets-proxy.php?action=datos";
  const response = await fetch(url);
  const apiResponse = await response.json();
  const values = apiResponse.data || [];
  const jsonData = convertSheetDataToObjects(values);
  processData(jsonData);
}

async function loadEmergencyContactsFromSheet() {
  const url = "/bitacora_/src/api/google-sheets-proxy.php?action=contactos";

  try {
    const response = await fetch(url);
    const apiResponse = await response.json();
    const data = { values: apiResponse.data || [] };

    if (!data.values || data.values.length < 2) {
      return [];
    }

    const rows = data.values;
    const headers = rows[0].map((h) =>
      (h || "")
        .toString()
        .trim()
        .toLowerCase()
        .replace(/[^a-záéíóúñü\s]/g, ""),
    );

    // Mapear tus encabezados exactos:
    const headerMap = {
      "nombre contacto": "label",
      nombre: "label",
      "cargo/posicion": "cargo",
      cargo: "cargo",
      "area/departamento": "departamento",
      departamento: "departamento",
      "prioridad contacto": "prioridad",
      prioridad: "prioridad",
      telefonos: "telefonos",
      correos: "correos",
      accionesatomar: "acciones",
      acciones: "acciones",
      observaciones: "observaciones",
    };

    const records = rows.slice(1).map((row) => {
      const obj = {};

      headers.forEach((header, i) => {
        const normalizedHeader =
          Object.keys(headerMap).find(
            (key) => header.includes(key) || key.includes(header),
          ) || header;

        obj[headerMap[normalizedHeader] || normalizedHeader] = row[i] || "";
      });

      return {
        label: obj.label || "Contacto",
        nombre: obj.nombre || obj.label || "",
        cargo: obj.cargo || "",
        departamento: obj.departamento || "",
        prioridad: obj.prioridad || "Normal",
        telefonos: (obj.telefonos || "")
          .split(/[\n,;|]/) // Soporta comas, punto-coma, saltos de línea
          .map((t) => t.trim())
          .filter(Boolean),
        correos: (obj.correos || "")
          .split(/[\n,;|]/)
          .map((c) => c.trim())
          .filter(Boolean),
        acciones: obj.acciones || "",
        observaciones: obj.observaciones || "",
      };
    });

    return records.filter((r) => r.nombre && r.nombre.trim());
  } catch (error) {
    console.error("Error cargando contactos de emergencia:", error);
    return [];
  }
}

function convertSheetDataToObjects(data) {
  if (data.length < 1) return [];
  const columnMap = {
    "salida programada": "salida_prog",
    "carga programada": "carga_prog",
    "salida carga programada": "salida_carga_prog",
    "descarga programada": "descarga_prog",
    "id equipos": "id_equipos",
    teléfono: "telefono",
    telefono: "telefono",
  };
  const fallbackHeaders = [
    "fecha",
    "unidad",
    "placas",
    "id_equipos",
    "operador",
    "telefono",
    "ruta",
    "origen",
    "destino",
    "salida_prog",
    "carga_prog",
    "salida_carga_prog",
    "descarga_prog",
    "cliente",
  ];
  const firstCell = String(data[0][0] || "").trim();
  const looksLikeDate =
    /^\d{4}-\d{2}-\d{2}/.test(firstCell) ||
    /^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}/.test(firstCell);

  let headers;
  let rows;

  if (!looksLikeDate && data.length >= 2) {
    headers = data
      .shift()
      .map((h) => (typeof h === "string" ? h.trim().toLowerCase() : h));
    rows = data;
  } else {
    console.warn(
      "[Bitácora] La primera fila del proxy son datos, no encabezados. Usando mapeo fijo por índice. Corrige GOOGLE_SHEETS_RANGE_DATOS en .env del servidor para incluir la fila de headers.",
    );
    headers = fallbackHeaders;
    rows = data;
  }

  return rows
    .map((row) => {
      const obj = {};
      headers.forEach((header, index) => {
        if (header) {
          const mappedHeader = columnMap[header] || header;
          obj[mappedHeader] = row[index] || "";
        }
      });
      return obj;
    })
    .filter((row) => row.unidad && String(row.unidad).trim() !== "");
}

function readSavedMap() {
  try {
    const raw = localStorage.getItem("bitacoraData");
    const arr = raw ? JSON.parse(raw) : null;
    if (!Array.isArray(arr)) return new Map();
    const m = new Map();
    arr.forEach((d) => m.set(`${d.folio}-${d.unidad}-${d.fechaProgramada}`, d));
    return m;
  } catch (e) {
    return new Map();
  }
}

function processData(sheetRows) {
  const savedMap = readSavedMap();

  allDespachosData = sheetRows.map((row, idx) => {
    const fechaProg = excelDateToYYYYMMDD(row["fecha"]);
    const base = {
      folio: row["folio"] || idx + 1,
      fechaProgramada: fechaProg,
      unidad: row["unidad"] || "N/A",
      placas: row["placas"] || "N/A",
      operador: row["operador"] || "N/A",
      telefono: row["teléfono"] || row["telefono"] || "N/A",
      ruta: row["ruta"] || "N/A",
      origen: row["origen"] || "N/A",
      destino: row["destino"] || "N/A",
      citaSalidaUnidad: parseExcelDateTime(row["salida_prog"], row["fecha"]),
      citaCarga: parseExcelDateTime(row["carga_prog"], row["fecha"]),
      citaSalida: parseExcelDateTime(row["salida_carga_prog"], row["fecha"]),
      citaDescarga: parseExcelDateTime(row["descarga_prog"], row["fecha"]),
      cliente: row["cliente"] || "N/A",
      realSalidaUnidad: "",
      realCarga: "",
      realSalida: "",
      realDescarga: "",
      estatus: "Programado",
      incidencias: [],
      observaciones: "",
      operadorMonitoreoId: row["operador_monitoreo"] || "",
      gpsValidacionEstado: row["gps_validacion_estado"] || "",
      gpsValidacionTimestamp: row["gps_validacion_timestamp"] || "",
    };

    const key = `${base.folio}-${base.unidad}-${base.fechaProgramada}`;
    if (savedMap.has(key)) {
      const saved = savedMap.get(key);
      const merged = { ...base, ...saved };
      merged.folio = base.folio;
      merged.unidad = base.unidad;
      merged.fechaProgramada = base.fechaProgramada;
      merged.cliente = base.cliente;
      merged.placas = base.placas;
      merged.operador = base.operador;
      merged.telefono = base.telefono;
      merged.ruta = base.ruta;
      merged.origen = base.origen;
      merged.destino = base.destino;
      merged.citaSalidaUnidad = base.citaSalidaUnidad;
      merged.citaCarga = base.citaCarga;
      merged.citaSalida = base.citaSalida;
      merged.citaDescarga = base.citaDescarga;
      return merged;
    }
    return base;
  });

  saveAllData();

  const dateInput = document.getElementById("date-filter");
  if (!dateInput.value) {
    const dates = [
      ...new Set(
        allDespachosData.map((d) => d.fechaProgramada).filter(Boolean),
      ),
    ].sort((a, b) => dayjs(b).diff(dayjs(a)));
    dateInput.value = dates[0] || dayjs().format("YYYY-MM-DD");
  }
  filterByDate();

  try {
    Promise.all([loadSeguimientosFromDb(), loadOrigenDestinoFromDb()])
      .then(() => {
        renderAllTabs();
      })
      .catch((e) => {
        console.warn("Error cargando datos desde BD:", e);
      });
  } catch (e) {
    console.warn("Error iniciando carga desde BD:", e);
  }
}

function saveAllData() {
  try {
    localStorage.setItem("bitacoraData", JSON.stringify(allDespachosData));
  } catch (e) {}
}
function filterDataByCliente(data) {
  if (!userRole || !data || data.length === 0) {
    return [];
  }

  if (userUnidades.includes("*")) {
    return data;
  }

  return data.filter((item) => {
    const itemUnidad = String(item?.unidad || "")
      .trim()
      .toUpperCase();
    if (!itemUnidad) return false;
    return userUnidades.some((allowedUnidad) => {
      const allowed = String(allowedUnidad).trim().toUpperCase();
      return itemUnidad === allowed;
    });
  });
}

function filterByDate() {
  const selectedDate = document.getElementById("date-filter").value;
  if (!selectedDate) filteredDespachosData = [...allDespachosData];
  else
    filteredDespachosData = allDespachosData.filter(
      (d) => d.fechaProgramada === selectedDate,
    );

  filteredDespachosData = filterDataByCliente(filteredDespachosData);
  try {
    applySeguimientosDbToArray(filteredDespachosData);
  } catch (e) {
    console.warn("Error aplicando seguimientos desde BD:", e);
  }
  renderAllTabs();
}

function renderAllTabs() {
  renderDatosGenerales();
  renderOrigenDestino();
  populateUnidadSelector();
  populateRegistroFilter();
  renderRegistroDespacho();
  renderKPIs(filteredDespachosData);
  renderInforme(filteredDespachosData);
  renderIncidenciasExecutiveReport();
  renderValidacionesGPS();
  updateClienteDisplay();
}
function renderDatosGenerales() {
  const c = document.getElementById("datos-generales-container");
  c.innerHTML = "";

  actualizarFiltrosDatosGenerales();

  if (filteredDespachosData.length === 0) {
    c.innerHTML = `<div class="col-span-full text-center p-10 bg-white rounded-xl shadow"><h3 class="text-lg">No hay despachos para esta fecha</h3></div>`;
    actualizarContadorDatosGenerales(0, 0);
    return;
  }
  const filtroUnidad = document.getElementById("filtro-unidad-dg")?.value || "";
  const filtroMaxTramos = parseInt(
    document.getElementById("filtro-max-tramos")?.value || "0",
    10,
  );
  const filtroCliente =
    document.getElementById("filtro-cliente-dg")?.value || "";

  let datosFiltrados = filteredDespachosData;

  if (filtroUnidad) {
    datosFiltrados = datosFiltrados.filter(
      (d) => String(d.unidad || "").trim() === filtroUnidad,
    );
  }

  if (filtroCliente) {
    datosFiltrados = datosFiltrados.filter(
      (d) => String(d.cliente || "").trim() === filtroCliente,
    );
  }

  const unidadCount = {};
  const unidadIndex = {};
  datosFiltrados.forEach((d) => {
    const unidadNombre = String(d.unidad || "").trim();
    unidadCount[unidadNombre] = (unidadCount[unidadNombre] || 0) + 1;
    unidadIndex[unidadNombre] = 0;
  });

  let mostrados = 0;
  datosFiltrados.forEach((d) => {
    const unidadNombre = String(d.unidad || "").trim();
    const tieneMultiplesTramos = unidadCount[unidadNombre] > 1;
    unidadIndex[unidadNombre] = (unidadIndex[unidadNombre] || 0) + 1;
    const numeroTramo = unidadIndex[unidadNombre];

    if (filtroMaxTramos > 0 && numeroTramo > filtroMaxTramos) {
      return;
    }

    mostrados++;
    const tramoLabel = tieneMultiplesTramos
      ? ` - Tramo ${numeroTramo}${filtroMaxTramos > 0 && unidadCount[unidadNombre] > filtroMaxTramos ? ` de ${unidadCount[unidadNombre]}` : ""}`
      : "";

    c.innerHTML += `
                  <div class="bg-white p-5 rounded-xl shadow-lg border border-gray-100">
                    <div class="flex justify-between items-start mb-3">
                      <div>
                        <h3 class="font-bold text-lg text-blue-800">${escapeHtml(
                          d.unidad,
                        )}${tramoLabel}</h3>
                        <p class="text-sm text-gray-500">${escapeHtml(d.placas)}</p>
                      </div>
                      <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                        Folio: ${String(d.folio).padStart(3, "0")}
                      </span>
                    </div>
                    <div class="space-y-2 text-sm">
                      <p><strong>Operador:</strong> ${escapeHtml(d.operador)}</p>
                      <p><strong>Ruta:</strong> ${escapeHtml(d.ruta)}</p>
                      <p><strong>Destino:</strong> ${escapeHtml(d.destino)}</p>
                      <p><strong>Cliente:</strong> ${escapeHtml(d.cliente)}</p>
                    </div>
                  </div>`;
  });

  actualizarContadorDatosGenerales(mostrados, datosFiltrados.length);

  if (mostrados === 0) {
    c.innerHTML = `<div class="col-span-full text-center p-10 bg-white rounded-xl shadow"><h3 class="text-lg">No hay despachos que coincidan con los filtros</h3></div>`;
  }
}

function actualizarFiltrosDatosGenerales() {
  const selectUnidad = document.getElementById("filtro-unidad-dg");
  const selectCliente = document.getElementById("filtro-cliente-dg");

  if (!selectUnidad || !selectCliente) return;

  const unidadActual = selectUnidad.value;
  const clienteActual = selectCliente.value;

  const unidades = [
    ...new Set(
      filteredDespachosData
        .map((d) => String(d.unidad || "").trim())
        .filter((u) => u),
    ),
  ].sort();

  const clientes = [
    ...new Set(
      filteredDespachosData
        .map((d) => String(d.cliente || "").trim())
        .filter((c) => c),
    ),
  ].sort();
  selectUnidad.innerHTML = '<option value="">Todas las unidades</option>';
  unidades.forEach((u) => {
    const selected = u === unidadActual ? "selected" : "";
    selectUnidad.innerHTML += `<option value="${escapeHtml(u)}" ${selected}>${escapeHtml(u)}</option>`;
  });

  selectCliente.innerHTML = '<option value="">Todos los clientes</option>';
  clientes.forEach((c) => {
    const selected = c === clienteActual ? "selected" : "";
    selectCliente.innerHTML += `<option value="${escapeHtml(c)}" ${selected}>${escapeHtml(c)}</option>`;
  });
}

function actualizarContadorDatosGenerales(mostrados, total) {
  const contador = document.getElementById("contador-datos-generales");
  if (contador) {
    if (mostrados === total) {
      contador.textContent = `Mostrando ${mostrados} despachos`;
    } else {
      contador.textContent = `Mostrando ${mostrados} de ${total} despachos`;
    }
  }
}

function limpiarFiltrosDatosGenerales() {
  const selectUnidad = document.getElementById("filtro-unidad-dg");
  const selectMaxTramos = document.getElementById("filtro-max-tramos");
  const selectCliente = document.getElementById("filtro-cliente-dg");

  if (selectUnidad) selectUnidad.value = "";
  if (selectMaxTramos) selectMaxTramos.value = "3";
  if (selectCliente) selectCliente.value = "";

  renderDatosGenerales();
}

function renderOrigenDestino() {
  const c = document.getElementById("origen-destino-container");
  c.innerHTML = "";
  if (filteredDespachosData.length === 0) {
    c.innerHTML = `<div class="col-span-full text-center p-10 bg-white rounded-xl shadow"><h3 class="text-lg">Sin datos de rutas</h3></div>`;
    return;
  }

  const unidadCount = {};
  const unidadIndex = {};
  filteredDespachosData.forEach((d) => {
    const unidadNombre = String(d.unidad || "").trim();
    unidadCount[unidadNombre] = (unidadCount[unidadNombre] || 0) + 1;
    unidadIndex[unidadNombre] = 0;
  });

  const isEditor = userRole === "editor" || userRole === "admin";

  filteredDespachosData.forEach((d, index) => {
    const unidadNombre = String(d.unidad || "").trim();
    const tieneMultiplesTramos = unidadCount[unidadNombre] > 1;
    unidadIndex[unidadNombre] = (unidadIndex[unidadNombre] || 0) + 1;
    const numeroTramo = unidadIndex[unidadNombre];
    const tramoLabel = tieneMultiplesTramos ? ` - Tramo ${numeroTramo}` : "";

    const origenUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(
      d.origen || "",
    )}`;
    const destinoUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(
      d.destino || "",
    )}`;

    const editButton = isEditor
      ? `<button
                onclick="openEditOrigenDestinoModal(${index})"
                class="mt-3 w-full bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium transition"
              >
                Editar Origen/Destino
              </button>`
      : "";

    c.innerHTML += `
                  <div class="bg-white p-5 rounded-xl shadow border">
                    <h3 class="font-bold text-lg text-blue-800">${escapeHtml(
                      d.unidad,
                    )}${tramoLabel}</h3>
                    <p class="text-sm text-gray-500">${escapeHtml(d.ruta)}</p>
                    <p class="text-sm mt-2"><strong>Origen:</strong>
                      <a href="${origenUrl}" target="_blank" class="text-blue-600 underline">${escapeHtml(
                        d.origen || "N/A",
                      )}</a>
                    </p>
                    <p class="text-sm"><strong>Destino:</strong>
                      <a href="${destinoUrl}" target="_blank" class="text-blue-600 underline">${escapeHtml(
                        d.destino || "N/A",
                      )}</a>
                    </p>
                    <p class="text-sm"><strong>Cliente:</strong> ${escapeHtml(
                      d.cliente || "N/A",
                    )}</p>
                    ${editButton}
                  </div>`;
  });
}

function openEditOrigenDestinoModal(index) {
  const despacho = filteredDespachosData[index];
  if (!despacho) return;

  document.getElementById("edit-folio").value = despacho.folio || "";
  document.getElementById("edit-unidad").value = despacho.unidad || "";
  document.getElementById("edit-unidad-display").value = despacho.unidad || "";
  document.getElementById("edit-fecha-programada").value =
    despacho.fechaProgramada || "";
  document.getElementById("edit-origen").value = despacho.origen || "";
  document.getElementById("edit-destino").value = despacho.destino || "";

  document
    .getElementById("edit-origen-destino-modal")
    .classList.remove("hidden");
}

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("form-edit-origen-destino");
  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const folio = document.getElementById("edit-folio").value;
      const unidad = document.getElementById("edit-unidad").value;
      const fechaProgramada = document.getElementById(
        "edit-fecha-programada",
      ).value;
      const origen = document.getElementById("edit-origen").value.trim();
      const destino = document.getElementById("edit-destino").value.trim();

      if (!folio || !unidad || !fechaProgramada) {
        alert("Error: Faltan datos del despacho");
        return;
      }

      if (!origen && !destino) {
        alert("Debe ingresar al menos un origen o destino");
        return;
      }

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalText = submitBtn.textContent;
      submitBtn.disabled = true;
      submitBtn.textContent = "Guardando...";

      try {
        const response = await fetch(
          "/bitacora_/src/origen_destino/actualizar_origen_destino.php",
          {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              folio,
              unidad,
              fechaProgramada,
              origen,
              destino,
            }),
          },
        );

        const result = await response.json();

        if (result.success) {
          // Actualizar en memoria sin recargar Sheets
          const origenDestinoKey = `${String(folio).trim()}|${String(unidad).trim()}|${String(fechaProgramada).trim()}`;
          origenDestinoDbCache.map.set(origenDestinoKey, {
            folio,
            unidad,
            fechaProgramada,
            origen,
            destino,
          });
          origenDestinoDbCache.loaded = true;

          [allDespachosData, filteredDespachosData].forEach((arr) => {
            arr.forEach((d) => {
              const k = `${String(d.folio ?? "").trim()}|${String(d.unidad ?? "").trim()}|${String(d.fechaProgramada ?? "").trim()}`;
              if (k === origenDestinoKey) {
                d.origen = origen;
                d.destino = destino;
              }
            });
          });

          closeModal("edit-origen-destino-modal");
          renderOrigenDestino();
        } else {
          alert("Error: " + result.message);
        }
      } catch (error) {
        console.error("Error al actualizar origen/destino:", error);
        alert("Error al conectar con el servidor. Intente nuevamente.");
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    });
  }
});

// ── Editar Fecha Programada ─────────────────────────────────────────────
function openEditFechaProgramadaModal(index) {
  const d = filteredDespachosData[index];
  if (!d) return;

  document.getElementById("efp-folio").value = d.folio || "";
  document.getElementById("efp-unidad").value = d.unidad || "";
  document.getElementById("efp-fecha-actual").value = d.fechaProgramada || "";
  document.getElementById("efp-index").value = index;
  document.getElementById("efp-unidad-display").value = d.unidad || "";
  document.getElementById("efp-fecha-actual-display").value = d.fechaProgramada
    ? dayjs(d.fechaProgramada).format("DD/MM/YYYY")
    : "";
  document.getElementById("efp-fecha-nueva").value = d.fechaProgramada || "";

  openModal("edit-fecha-programada-modal");
}

document.addEventListener("DOMContentLoaded", () => {
  const formEfp = document.getElementById("form-edit-fecha-programada");
  if (!formEfp) return;

  formEfp.addEventListener("submit", async (e) => {
    e.preventDefault();

    const folio = document.getElementById("efp-folio").value;
    const unidad = document.getElementById("efp-unidad").value;
    const fechaActual = document.getElementById("efp-fecha-actual").value;
    const fechaNueva = document.getElementById("efp-fecha-nueva").value;
    const index = parseInt(document.getElementById("efp-index").value, 10);

    if (!fechaNueva) {
      alert("Seleccione una nueva fecha.");
      return;
    }

    if (fechaNueva === fechaActual) {
      closeModal("edit-fecha-programada-modal");
      return;
    }

    const submitBtn = document.getElementById("efp-submit-btn");
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = "Guardando...";

    // Helper: remplaza la parte de fecha en un datetime string YYYY-MM-DD HH:mm:ss
    function swapDatePart(datetimeStr, newDate) {
      if (!datetimeStr || !String(datetimeStr).trim()) return datetimeStr;
      const d = dayjs(datetimeStr);
      if (!d.isValid()) return datetimeStr;
      return newDate + " " + d.format("HH:mm:ss");
    }

    try {
      // 1. Actualizar MySQL
      const response = await fetch(
        "/bitacora_/src/seguimiento/actualizar_fecha_programada.php",
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            folio: folio,
            unidad: unidad,
            fechaProgramadaActual: fechaActual,
            fechaProgramadaNueva: fechaNueva,
          }),
        },
      );

      const result = await response.json();

      if (!result.success) {
        alert("Error al actualizar en base de datos: " + result.message);
        return;
      }

      // 2. Actualizar Google Sheets (en paralelo, no bloquea el flujo)
      const sheetsPromise = fetch(
        "/bitacora_/src/api/actualizar_fecha_sheets.php",
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            unidad: unidad,
            fechaActual: fechaActual,
            fechaNueva: fechaNueva,
          }),
        },
      )
        .then((r) => r.json())
        .catch((err) => ({
          success: false,
          message: err.message,
        }));

      // 3. Actualizar estado en memoria

      // Función auxiliar para actualizar cita* de un registro
      function actualizarCitas(rec) {
        rec.fechaProgramada = fechaNueva;
        rec.citaSalidaUnidad = swapDatePart(rec.citaSalidaUnidad, fechaNueva);
        rec.citaCarga = swapDatePart(rec.citaCarga, fechaNueva);
        rec.citaSalida = swapDatePart(rec.citaSalida, fechaNueva);
        rec.citaDescarga = swapDatePart(rec.citaDescarga, fechaNueva);
      }

      // Actualizar allDespachosData
      const allIdx = allDespachosData.findIndex(
        (d) =>
          String(d.folio) === String(folio) &&
          d.unidad === unidad &&
          d.fechaProgramada === fechaActual,
      );
      if (allIdx !== -1) actualizarCitas(allDespachosData[allIdx]);

      // Actualizar filteredDespachosData en el lugar
      if (filteredDespachosData[index]) {
        actualizarCitas(filteredDespachosData[index]);
      }

      // Actualizar seguimientosDbCache
      const oldKey = makeSeguimientoKey(folio, unidad, fechaActual);
      const newKey = makeSeguimientoKey(folio, unidad, fechaNueva);
      if (seguimientosDbCache.map.has(oldKey)) {
        const cached = seguimientosDbCache.map.get(oldKey);
        cached.fechaProgramada = fechaNueva;
        seguimientosDbCache.map.delete(oldKey);
        seguimientosDbCache.map.set(newKey, cached);
      }

      // Persistir en localStorage
      saveAllData();

      // 4. Actualizar filtro de fecha y re-renderizar
      const dateFilter = document.getElementById("date-filter");
      if (dateFilter) dateFilter.value = fechaNueva;
      filterByDate();

      // Re-seleccionar la unidad en el selector tras el re-filtrado
      const newIndex = filteredDespachosData.findIndex(
        (d) =>
          String(d.folio) === String(folio) &&
          d.unidad === unidad &&
          d.fechaProgramada === fechaNueva,
      );
      if (newIndex !== -1) {
        _syncUnidadSelectorByIndex(newIndex);
        renderSeguimientoForm(newIndex);
      }

      closeModal("edit-fecha-programada-modal");

      // 5. Esperar resultado de Google Sheets y notificar
      const sheetsResult = await sheetsPromise;
      if (sheetsResult.success) {
        alert(
          "Fecha programada actualizada correctamente en base de datos y Google Sheets.",
        );
      } else {
        alert(
          "Fecha actualizada en base de datos.\n\n" +
            "⚠️ No se pudo actualizar Google Sheets: " +
            sheetsResult.message +
            "\n\nVerifica que GOOGLE_APPS_SCRIPT_URL esté configurado en el .env.",
        );
      }
    } catch (error) {
      console.error("Error al actualizar fecha programada:", error);
      alert("Error al conectar con el servidor. Intente nuevamente.");
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
  });
});
// ── Fin: Editar Fecha Programada ────────────────────────────────────────

function renderClienteFilter() {
  const container = document.getElementById("cliente-filter-container");
  if (!container) return;

  const clientes = [
    ...new Set(
      filteredDespachosData.map((d) => d.cliente).filter((c) => c && c.trim()),
    ),
  ].sort();

  let html = `
              <div class="mb-4">
                <label class="block mb-2 font-semibold">Filtrar por Cliente:</label>
                <select id="cliente-filter" onchange="filterByCliente()"
                        class="border px-3 py-2 rounded w-full max-w-md">
                  <option value="">Todos los clientes</option>
            `;

  clientes.forEach((cliente) => {
    html += `<option value="${escapeHtml(cliente)}">${escapeHtml(
      cliente,
    )}</option>`;
  });

  html += `</select></div>`;
  container.innerHTML = html;
}

function filterByCliente() {
  const select = document.getElementById("cliente-filter");
  const clienteSeleccionado = select?.value || "";

  if (!clienteSeleccionado) {
    populateUnidadSelector();
    return;
  }

  const unidadesFiltradas = filteredDespachosData.filter(
    (d) => d.cliente === clienteSeleccionado,
  );
  const unidadCount = {};
  const unidadIndex = {};
  unidadesFiltradas.forEach((d) => {
    const unidadNombre = String(d.unidad || "").trim();
    unidadCount[unidadNombre] = (unidadCount[unidadNombre] || 0) + 1;
    unidadIndex[unidadNombre] = 0;
  });

  const unidadSelect = document.getElementById("unidad-select");
  if (!unidadSelect) return;

  unidadSelect.innerHTML = '<option value="">-- Selecciona Unidad --</option>';

  unidadesFiltradas.forEach((d) => {
    const unidadNombre = String(d.unidad || "").trim();
    const tieneMultiplesTramos = unidadCount[unidadNombre] > 1;
    unidadIndex[unidadNombre] = (unidadIndex[unidadNombre] || 0) + 1;
    const numeroTramo = unidadIndex[unidadNombre];
    const tramoLabel = tieneMultiplesTramos ? ` - Tramo ${numeroTramo}` : "";

    const opt = document.createElement("option");
    opt.value = d.index;
    opt.textContent = `${d.unidad}${tramoLabel} - ${d.operador}`;
    unidadSelect.appendChild(opt);
  });
}
function populateUnidadSelector() {
  const nombreSel = document.getElementById("unidad-nombre-selector");
  const tramoSel = document.getElementById("unidad-tramo-selector");
  if (!nombreSel) return;

  // Collect unique unit names preserving order of first appearance
  const seen = new Set();
  const uniqueUnidades = [];
  filteredDespachosData.forEach((d) => {
    const nombre = String(d.unidad || "").trim();
    if (nombre && !seen.has(nombre)) {
      seen.add(nombre);
      uniqueUnidades.push(nombre);
    }
  });

  nombreSel.innerHTML = '<option value="">-- Seleccione unidad --</option>';
  uniqueUnidades.forEach((nombre) => {
    nombreSel.innerHTML += `<option value="${escapeHtml(nombre)}">${escapeHtml(nombre)}</option>`;
  });

  // Reset tramo selector
  tramoSel.innerHTML = "";
  tramoSel.classList.add("hidden");

  document.getElementById("consulta-btn").disabled = true;
  document.getElementById("seguimiento-form-container").classList.add("hidden");
}

function handleUnidadNombreSelection(ev) {
  const nombre = ev.target.value;
  const tramoSel = document.getElementById("unidad-tramo-selector");
  const btn = document.getElementById("consulta-btn");

  // Reset tramo selector and form
  tramoSel.innerHTML = "";
  tramoSel.classList.add("hidden");
  btn.disabled = true;
  document.getElementById("seguimiento-form-container").classList.add("hidden");

  if (!nombre) return;

  // Find all tramos for this unit
  const tramos = [];
  filteredDespachosData.forEach((d, i) => {
    if (String(d.unidad || "").trim() === nombre) {
      tramos.push({ idx: i, num: tramos.length + 1 });
    }
  });

  if (tramos.length === 1) {
    // Single tramo: auto-select and render immediately
    btn.disabled = false;
    renderSeguimientoForm(tramos[0].idx);
  } else {
    // Multiple tramos: show tramo selector
    tramoSel.innerHTML = '<option value="">-- Seleccione tramo --</option>';
    tramos.forEach((t) => {
      tramoSel.innerHTML += `<option value="${t.idx}">Tramo ${t.num}</option>`;
    });
    tramoSel.classList.remove("hidden");
  }
}

function handleUnidadSelection(ev) {
  const idx = ev.target.value;
  const btn = document.getElementById("consulta-btn");
  if (idx === "") {
    btn.disabled = true;
    document
      .getElementById("seguimiento-form-container")
      .classList.add("hidden");
    return;
  }
  btn.disabled = false;
  renderSeguimientoForm(Number(idx));
}

function _syncUnidadSelectorByIndex(index) {
  const d = filteredDespachosData[index];
  if (!d) return;
  const nombre = String(d.unidad || "").trim();

  const nombreSel = document.getElementById("unidad-nombre-selector");
  const tramoSel = document.getElementById("unidad-tramo-selector");
  const btn = document.getElementById("consulta-btn");
  if (!nombreSel) return;

  nombreSel.value = nombre;

  // Rebuild tramo list for this unit
  const tramos = [];
  filteredDespachosData.forEach((d2, i) => {
    if (String(d2.unidad || "").trim() === nombre) {
      tramos.push({ idx: i, num: tramos.length + 1 });
    }
  });

  if (tramos.length > 1) {
    tramoSel.innerHTML = '<option value="">-- Seleccione tramo --</option>';
    tramos.forEach((t) => {
      tramoSel.innerHTML += `<option value="${t.idx}">Tramo ${t.num}</option>`;
    });
    tramoSel.value = String(index);
    tramoSel.classList.remove("hidden");
  } else {
    tramoSel.classList.add("hidden");
    tramoSel.innerHTML = "";
  }

  if (btn) btn.disabled = false;
}

function _getCurrentUnidadIdx() {
  const tramoSel = document.getElementById("unidad-tramo-selector");
  const nombreSel = document.getElementById("unidad-nombre-selector");

  // If tramo selector is visible, use its value
  if (
    tramoSel &&
    !tramoSel.classList.contains("hidden") &&
    tramoSel.value !== ""
  ) {
    return Number(tramoSel.value);
  }

  // Single-tramo case: find the index by unit name
  const nombre = nombreSel ? nombreSel.value : "";
  if (!nombre) return null;
  const found = filteredDespachosData.findIndex(
    (d) => String(d.unidad || "").trim() === nombre,
  );
  return found >= 0 ? found : null;
}

function showUnitData() {
  const idx = _getCurrentUnidadIdx();
  if (idx === null) return;
  const d = filteredDespachosData[Number(idx)];
  document.getElementById("modal-body").innerHTML = `
                <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                  <p><strong>Folio:</strong></p><p>${escapeHtml(
                    String(d.folio).padStart(3, "0"),
                  )}</p>
                  <p><strong>Unidad:</strong></p><p>${escapeHtml(d.unidad)}</p>
                  <p><strong>Placas:</strong></p><p>${escapeHtml(d.placas)}</p>
                  <p><strong>Operador:</strong></p><p>${escapeHtml(d.operador)}</p>
                  <p><strong>Teléfono:</strong></p><p>${escapeHtml(d.telefono)}</p>
                  <p><strong>Ruta:</strong></p><p>${escapeHtml(d.ruta)}</p>
                  <p><strong>Origen:</strong></p><p>${escapeHtml(d.origen)}</p>
                  <p><strong>Destino:</strong></p><p>${escapeHtml(d.destino)}</p>
                  <p><strong>Cliente:</strong></p><p>${escapeHtml(d.cliente)}</p>
                </div>`;
  openModal("data-modal");
}

function getIncidenciaSeveridad(tipo) {
  const map = {
    "Desconexión de batería": "Rojo",
    "Botón de pánico": "Rojo",
    Persecución: "Rojo",
    "Accidente vehicular propio": "Rojo",
    "Robo o asalto": "Rojo",
    "Despacho No Recibido": "Rojo",
    "Ausencia de actualización": "Naranja",
    "Parada no autorizada": "Naranja",
    "Operador no contesta más de 2 veces": "Naranja",
    "Detención por faltas al reglamento de tránsito": "Naranja",
    "Falla mecánica": "Naranja",
    "Ponchadura de llantas": "Naranja",
    "Cierres carreteros": "Naranja",
    "Condiciones climatológicas": "Naranja",
    "Sin contacto con el operador": "Naranja",
    "Desvío de ruta": "Naranja",
    "Vehículo sospechoso": "Naranja",
    "Salida a destiempo": "Verde",
    "Desconocimiento de lugar de entrega": "Verde",
    "Mala actitud del Operador": "Verde",
  };
  return map[tipo] || "Verde";
}

function renderIncidencias(incs, despachoIndex, isEditor) {
  const list = Array.isArray(incs) ? incs : [];
  if (!list.length)
    return `<p class="text-sm text-gray-500">No hay incidencias.</p>`;

  return list
    .map((inc, incIndex) => {
      const tipo = inc && inc.tipo ? String(inc.tipo) : "Incidencia";
      const sev =
        inc && inc.severidad
          ? String(inc.severidad)
          : getIncidenciaSeveridad(tipo);
      const sevLower = sev.toLowerCase();
      const cls =
        sevLower === "rojo"
          ? "incidencia-rojo"
          : sevLower === "naranja"
            ? "incidencia-naranja"
            : "incidencia-verde";

      return `
                  <div class="text-sm p-2 rounded-md ${cls} flex items-center justify-between gap-3">
                    <div class="min-w-0">
                      <strong>${escapeHtml(tipo)}</strong>
                      <span class="opacity-80">— ${formatDateTime(
                        inc && inc.fecha ? inc.fecha : "",
                      )}</span>
                      ${
                        inc && inc.direccion
                          ? `<div class="text-xs opacity-80 mt-1">Dirección: ${escapeHtml(
                              inc.direccion,
                            )}</div>`
                          : ``
                      }
                    </div>
                    ${
                      isEditor
                        ? `<button type="button" onclick="deleteIncidencia(${despachoIndex}, ${incIndex})" class="p-1 text-red-700 hover:text-red-900" title="Eliminar incidencia">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M15 6V4c0-1-1-2-2-2h-2c-1 0-2 1-2 2v2"/>
                            </svg>
                          </button>`
                        : ``
                    }
                  </div>`;
    })
    .join("");
}

function deleteIncidencia(despachoIndex, incIndex) {
  if (userRole !== "editor" && userRole !== "admin") return;
  const d = filteredDespachosData[despachoIndex];
  if (!d || !Array.isArray(d.incidencias)) return;
  d.incidencias.splice(incIndex, 1);
  saveAllData();
  const el = document.getElementById("incidencias-list");
  if (el) el.innerHTML = renderIncidencias(d.incidencias, despachoIndex, true);

  updateGuardarButtonState(despachoIndex);

  renderRegistroDespacho();
  renderIncidenciasExecutiveReport();
}

const INCIDENCIA_PROTOCOLOS = {
  "Desconexión de batería": {
    nivel: "Crítica",
    pasos: [
      "Verificar si hubo corte de energía/tamper y hora del último ping válido.",
      "Llamar al operador (1 intento inmediato). Si no responde en 5–10 min → tratar como Crítica.",
      "Escalar a Seguridad patrimonial + Jefe de tráfico.",
      "Activar seguimiento alterno (último punto, geocercas dinámicas, etc.).",
      "Si coincide con desvío/parada/zona de riesgo → proceder como “Robo/Asalto”.",
    ],
  },
  "Botón de pánico": {
    nivel: "Crítica",
    pasos: [
      "Confirmar evento en plataforma (hora, ubicación, velocidad, rumbo).",
      "Escalar de inmediato a Seguridad + Operaciones.",
      "Intentar contacto breve con el operador (pregunta cerrada y validación con palabra clave, si existe).",
      "Mantener rastreo en vivo y registrar cambios de ruta/paradas.",
      "Activar protocolo externo (911) y notificar aseguradora/cliente si corresponde.",
    ],
  },
  Persecución: {
    nivel: "Crítica",
    pasos: [
      "Escalar inmediato a Seguridad + Operaciones.",
      "Mantener rastreo en vivo; guardar evidencias (ruta, timestamps, eventos).",
      "Indicar al operador dirigirse a punto seguro sin detenerse (según política).",
      "Activar autoridades/911 según política y ubicación.",
      "Comunicación controlada y bitácora detallada.",
    ],
  },
  "Accidente vehicular propio": {
    nivel: "Crítica",
    pasos: [
      "Confirmar estado del operador y ocupantes (lesiones / necesidad de emergencia).",
      "Si hay lesionados: activar emergencias (911) según política.",
      "Escalar a Operaciones + Seguridad (y aseguradora).",
      "Coordinar grúa/taller y continuidad (transbordo si aplica).",
      "Cierre con reporte y evidencias.",
    ],
  },
  "Robo o asalto": {
    nivel: "Crítica",
    pasos: [
      "Escalar inmediato a Seguridad + Operaciones (y aseguradora).",
      "Mantener seguimiento en vivo y preservar evidencias.",
      "Activar autoridades/911 según política y ubicación.",
      "Gestionar comunicaciones con cliente y continuidad (plan alterno).",
    ],
  },
  "Despacho No Recibido": {
    nivel: "Crítica",
    pasos: [
      "Confirmar datos del despacho: unidad, folio, destino, ventana de entrega y contacto del cliente.",
      "Validar estatus en plataforma (última ubicación GPS, ruta, geocerca de entrega, tiempos).",
      "Contactar al operador (mínimo 2 intentos) y documentar hora/resultado.",
      "Contactar al cliente / punto de entrega para confirmar si hay intento de entrega o rechazo.",
      "Solicitar evidencia disponible: confirmación verbal, fotos, sello, firma, número de recepción o referencia (si aplica).",
      "Si hay discrepancia (cliente indica NO recibido) y la unidad estuvo en zona de entrega: escalar a Seguridad/Tráfico y activar investigación interna.",
      "Si NO hay ubicación consistente o hay señales de riesgo: escalar inmediatamente a Seguridad según política y activar cadena de comunicación.",
      "Registrar en observaciones el resumen y acordar siguientes pasos (reintento, retorno, aclaración, levantamiento de reporte).",
    ],
  },

  "Ausencia de actualización": {
    nivel: "Relevante",
    pasos: [
      "Confirmar último ping y calidad de señal.",
      "Contactar operador y/o proveedor GPS si hay falla general.",
      "Escalar según umbrales y contexto (zona/horario/carga).",
    ],
  },
  "Parada no autorizada": {
    nivel: "Relevante",
    pasos: [
      "Validar duración y lugar (geocerca, punto autorizado, etc.).",
      "Contactar: motivo + tiempo estimado + confirmación de integridad (unidad/carga).",
      "Si no hay contacto o se prolonga: escalar a Tráfico; si hay riesgo → Seguridad.",
      "Registrar y ajustar ETA.",
    ],
  },
  "Operador no contesta más de 2 veces": {
    nivel: "Relevante",
    pasos: [
      "Hacer 2 intentos en 5–7 min por canales distintos.",
      "Revisar simultáneamente: desvío, paradas, ausencia de actualización, geocercas.",
      "Escalar a Tráfico; si hay condiciones de riesgo → Seguridad.",
    ],
  },
  "Detención por faltas al reglamento de tránsito": {
    nivel: "Relevante",
    pasos: [
      "Confirmar ubicación y estatus (¿detenido formalmente o infracción?).",
      "Escalar a Tráfico/Legal interno (según estructura).",
      "Ajustar ETA y notificar cliente si afecta ventana.",
      "Registrar evidencia y datos de la autoridad (si aplica).",
    ],
  },
  "Falla mecánica": {
    nivel: "Relevante",
    pasos: [
      "Indicar al operador ubicarse en punto seguro y activar intermitentes.",
      "Coordinar asistencia vial y plan de continuidad (grúa/transbordo).",
      "Actualizar ETA y notificar afectaciones.",
      "Si zona de riesgo: involucrar Seguridad.",
    ],
  },
  "Ponchadura de llantas": {
    nivel: "Relevante",
    pasos: [
      "Confirmar ubicación segura para detenerse.",
      "Activar asistencia y evaluar transbordo si impacta.",
      "Registrar tiempo fuera de operación y ajustar ETA.",
    ],
  },
  "Cierres carreteros": {
    nivel: "Relevante",
    pasos: [
      "Confirmar tramo afectado y ruta alterna autorizada.",
      "Aprobar desvío formal.",
      "Ajustar ETA, notificar cliente y registrar causa externa.",
    ],
  },
  "Condiciones climatológicas": {
    nivel: "Relevante",
    pasos: [
      "Evaluar riesgo: reducir velocidad/pausar operación según política.",
      "Ajustar ruta/ETA y notificar al cliente.",
      "Definir punto seguro si se decide paro preventivo.",
      "Registrar evento para análisis.",
    ],
  },
  "Sin contacto con el operador": {
    nivel: "Relevante",
    pasos: [
      "Hacer 2 intentos (llamada + mensaje) en 5–7 min.",
      "Verificar si hay zonas sin cobertura / ausencia de actualización.",
      "Escalar a Coordinador/Tráfico; si condición sospechosa → Seguridad.",
      "Definir acción (punto seguro / verificación física, según operación).",
    ],
  },
  "Desvío de ruta": {
    nivel: "Relevante",
    pasos: [
      "Medir distancia/tiempo fuera de ruta y si se aleja del destino.",
      "Contactar operador: motivo, ruta alterna y nueva ETA.",
      "Si no hay justificación o no responde: escalar; si empeora → Seguridad.",
      "Actualizar ETA y notificar si impacta entrega.",
    ],
  },
  "Vehículo sospechoso": {
    nivel: "Relevante",
    pasos: [
      "Contactar operador y pedir descripción breve (sin distraer).",
      "Indicar mantenerse en vías principales y dirigirse a punto seguro.",
      "Escalar a Seguridad para acompañamiento remoto.",
      "Monitorear patrones: velocidad, paradas, cambios bruscos.",
    ],
  },

  "Salida a destiempo": {
    nivel: "Ordinaria",
    pasos: [
      "Recalcular ETA y notificar al cliente/almacén.",
      "Identificar causa y corregir.",
      "Registrar para mejora (KPI puntualidad).",
    ],
  },
  "Desconocimiento de lugar de entrega": {
    nivel: "Ordinaria",
    pasos: [
      "Proveer indicaciones y contacto del cliente.",
      "Si no hay contacto: escalar y definir punto de espera seguro.",
      "Documentar para actualizar instrucciones de entrega.",
    ],
  },
  "Mala actitud del Operador": {
    nivel: "Ordinaria",
    pasos: [
      "Interacción breve y profesional: recordar procedimiento.",
      "Registrar incidente y evidencias (si aplica).",
      "Escalar a Supervisor/Operaciones para seguimiento.",
      "Si hay negativa a cumplir instrucciones críticas → elevar nivel según riesgo.",
    ],
  },
};

function showProtocoloIncidenciaFromSelect() {
  const sel = document.getElementById("incidencia-tipo");
  const tipo = sel ? sel.value : "";
  if (!tipo)
    return alert("Seleccione un tipo de incidencia para ver el protocolo.");
  showProtocoloPorTipo(tipo);
}

function showProtocoloPorTipo(tipo) {
  const item = INCIDENCIA_PROTOCOLOS[tipo];
  const titleEl = document.getElementById("protocolo-modal-title");
  const bodyEl = document.getElementById("protocolo-modal-body");
  if (!titleEl || !bodyEl) return;

  titleEl.textContent = `Protocolo: ${tipo}`;
  if (!item) {
    bodyEl.innerHTML = `<p class="text-gray-600">No hay protocolo configurado para esta incidencia.</p>`;
    openModal("protocolo-modal");
    return;
  }

  const badgeClass =
    item.nivel === "Crítica"
      ? "bg-red-100 text-red-800"
      : item.nivel === "Relevante"
        ? "bg-orange-100 text-orange-800"
        : "bg-green-100 text-green-800";
  bodyEl.innerHTML = `
                <div class="mb-4">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold ${badgeClass}">
                    ${item.nivel}
                  </span>
                </div>
                <ol class="list-decimal ml-5 space-y-2 text-gray-700">
                  ${(item.pasos || [])
                    .map((p) => `<li>${escapeHtml(p)}</li>`)
                    .join("")}
                </ol>`;
  openModal("protocolo-modal");
}

function renderSeguimientoForm(index) {
  const container = document.getElementById("seguimiento-form-container");
  const d = filteredDespachosData[index];
  if (!d) {
    container.innerHTML = "<p>No hay datos.</p>";
    container.classList.remove("hidden");
    return;
  }

  const isEditor = userRole === "editor" || userRole === "admin";
  const disabled = isEditor ? "" : "disabled";
  const readOnly = isEditor ? "" : "readonly";

  const incidenciaOptions = `
                <option value="">-- Seleccione incidencia --</option>
                <optgroup label="🔴 Críticas (Rojo)">
                  <option>Desconexión de batería</option>
                  <option>Botón de pánico</option>
                  <option>Persecución</option>
                  <option>Accidente vehicular propio</option>
                  <option>Robo o asalto</option>
                  <option>Despacho No Recibido</option>
                </optgroup>
                <optgroup label="🟠 Relevantes (Naranja)">
                  <option>Ausencia de actualización</option>
                  <option>Parada no autorizada</option>
                  <option>Operador no contesta más de 2 veces</option>
                  <option>Detención por faltas al reglamento de tránsito</option>
                  <option>Falla mecánica</option>
                  <option>Ponchadura de llantas</option>
                  <option>Cierres carreteros</option>
                  <option>Condiciones climatológicas</option>
                  <option>Sin contacto con el operador</option>
                  <option>Desvío de ruta</option>
                  <option>Vehículo sospechoso</option>
                </optgroup>
                <optgroup label="🟢 Ordinarias (Verde)">
                  <option>Salida a destiempo</option>
                  <option>Desconocimiento de lugar de entrega</option>
                  <option>Mala actitud del Operador</option>
                </optgroup>`;

  const timeField = (label, name, realIso, progIso) => {
    const hasValue = realIso && realIso.trim();
    const containerClass = hasValue
      ? "field-programada"
      : "field-no-programada";
    const badgeHtml = hasValue
      ? ""
      : `
                  <span class="badge-no-programada" id="badge-${name}">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    NO PROGRAMADA
                  </span>
                `;

    const progName = name.replace(/^real/, "cita");
    return `
                <div class="${containerClass} p-4 transition-all duration-300" id="container-${name}">
                  <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">${label}</label>
                    ${badgeHtml}
                  </div>
                  <input
                    name="${name}"
                    type="datetime-local"
                    value="${formatForInput(realIso)}"
                    class="mt-1 w-full p-2 border rounded-md"
                    ${readOnly}
                    onchange="updateTimeFieldStatus('${name}'); updateEstatusFromForm();"
                    oninput="updateTimeFieldStatus('${name}')"
                  />
                  <div class="mt-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Programada:</label>
                    <input
                      name="${progName}"
                      type="datetime-local"
                      value="${formatForInput(progIso)}"
                      class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-50"
                      ${readOnly}
                    />
                  </div>
                </div>
                `;
  };

  container.innerHTML = `
                 <form id="form-seguimiento" class="space-y-6">
                   <!-- Encabezado: info del despacho + botón editar fecha -->
                   

                   <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 border rounded-xl p-4">
                      <label class="block text-sm font-semibold text-gray-700 mb-2">ID del Operador de Monitoreo</label>
                      <select name="operadorMonitoreoId" class="w-full p-2 border rounded-md" ${disabled}>
                        <option value="">-- Seleccione --</option>
                        ${[
                          "GEO-01",
                          "GEO-02",
                          "GEO-03",
                          "GEO-04",
                          "GEO-05",
                          "GEO-06",
                        ]
                          .map(
                            (v) =>
                              `<option value="${v}" ${
                                d.operadorMonitoreoId === v ? "selected" : ""
                              }>${v}</option>`,
                          )
                          .join("")}
                      </select>
                    </div>

                    <div class="bg-gray-50 border rounded-xl p-4">
                      <label class="block text-sm font-semibold text-gray-700 mb-2">Validación GPS y accesorios</label>
                      <div class="space-y-2 mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
                        ${renderGpsCheckboxes(d.gpsValidacionEstado, disabled)}
                      </div>
                      <p class="mt-3 text-xs text-gray-500">Última validación: <span class="font-semibold">${
                        d.gpsValidacionTimestamp
                          ? formatDateTime(d.gpsValidacionTimestamp)
                          : "Sin registro"
                      }</span></p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    ${timeField(
                      "Salida inicial de la Unidad (Real)",
                      "realSalidaUnidad",
                      d.realSalidaUnidad,
                      d.citaSalidaUnidad,
                    )}
                    ${timeField(
                      "Cita de Carga (Real)",
                      "realCarga",
                      d.realCarga,
                      d.citaCarga,
                    )}
                    ${timeField(
                      "Salida de carga (Real)",
                      "realSalida",
                      d.realSalida,
                      d.citaSalida,
                    )}
                    ${timeField(
                      "Proceso de Descarga (Real)",
                      "realDescarga",
                      d.realDescarga,
                      d.citaDescarga,
                    )}
                  </div>

                  <div class="bg-gray-50 border-2 rounded-xl p-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Estatus del Despacho</label>

                    <!-- Estatus automático calculado -->
                    <div class="bg-white border-2 border-indigo-200 rounded-lg p-4 mb-3">
                      <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-600">Progreso del Despacho:</span>
                        <span id="progreso-porcentaje" class="text-sm font-bold text-indigo-600">${calcularProgreso(d)}%</span>
                      </div>
                      <div class="progress-bar mb-3">
                        <div class="progress-fill" style="width: ${calcularProgreso(d)}%"></div>
                      </div>
                      <div id="estatus-badge-container">
                        ${renderEstatusBadge(calcularEstatusAutomatico(d))}
                      </div>
                    </div>

                    <!-- Estado especial (Cancelado / No realizado) -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Estado Especial (opcional)</label>
                      <select name="estatus_especial" class="mt-1 w-full p-2.5 border-2 rounded-lg text-base" ${disabled} onchange="updateEstatusFromForm()">
                        <option value="">-- Sin estado especial --</option>
                        <option value="Cancelado" ${
                          d.estatus_especial === "Cancelado" ||
                          d.estatus === "Cancelado"
                            ? "selected"
                            : ""
                        }>🚫 Cancelado</option>
                        <option value="Despacho No realizado" ${
                          d.estatus_especial === "Despacho No realizado" ||
                          d.estatus === "Despacho No realizado"
                            ? "selected"
                            : ""
                        }>❌ Despacho No realizado</option>
                      </select>
                      <p class="text-xs text-gray-500 mt-1">
                        Solo seleccione si el despacho fue cancelado o no se realizó. El estatus normal se calcula automáticamente.
                      </p>
                    </div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium">Observaciones</label>
                    <textarea name="observaciones" rows="3" class="mt-1 w-full p-2 border rounded-md" ${readOnly}>${escapeHtml(
                      d.observaciones || "",
                    )}</textarea>
                  </div>

                  <div class="mt-4 border-t pt-4 grid grid-cols-1 gap-4">
                    <div>
                      <div class="flex items-center justify-between flex-wrap gap-3 mb-3">
                        <div class="text-sm text-gray-600">
                          Incidencias: <strong>${
                            (d.incidencias || []).length
                          }</strong>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                          <select id="incidencia-tipo" class="p-2 border rounded-md w-72" ${disabled} onchange="handleIncidenciaTipoChange()">
                            ${incidenciaOptions}
                          </select>

                          <div id="incidencia-direccion-wrapper" class="hidden w-full md:w-[32rem]">

                          <label class="block text-sm font-medium text-gray-700">Dirección de ocurrencia</label>

                          <input

                            id="incidencia-direccion"

                            type="text"

                            placeholder="Ej. Km 43 Aut. México-Querétaro, Col. ..., Municipio, Estado"

                            class="mt-1 w-full p-2 border rounded-md"

                            ${disabled}

                          />

                          <p class="text-xs text-gray-500 mt-1">

                            Se guardará junto a la incidencia y se verá en el Informe Ejecutivo.

                          </p>

                        </div>

                          <button type="button" onclick="addIncidencia(${index})" ${disabled}
                            class="bg-slate-700 text-white px-4 py-2 rounded-md hover:bg-slate-800 disabled:bg-gray-400">
                            Agregar
                          </button>

                          <button type="button" onclick="showProtocoloIncidenciaFromSelect()"
                            class="bg-emerald-600 text-white px-4 py-2 rounded-md hover:bg-emerald-700">
                            Ver protocolo
                          </button>

                          <button type="button" id="btn-guardar-seguimiento-${index}" onclick="saveSeguimiento(${index})" ${disabled}
                            class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 disabled:bg-gray-400">
                            Guardar
                          </button>
                        </div>
                      </div>

                      <div class="space-y-2" id="incidencias-list">
                        ${renderIncidencias(d.incidencias || [], index, isEditor)}
                      </div>
                    </div>
                  </div>
                </form>`;
  container.classList.remove("hidden");
  setTimeout(() => {
    handleIncidenciaTipoChange();
    updateGuardarButtonState(index);
  }, 0);
}

function updateGuardarButtonState(index) {
  const btn = document.getElementById(`btn-guardar-seguimiento-${index}`);
  if (!btn || (userRole !== "editor" && userRole !== "admin")) return;

  btn.disabled = false;
  btn.title = "Guardar seguimiento";
}

function renderGpsCheckboxes(currentValue, disabled) {
  const opciones = [
    { id: "boton_panico", label: "Botón de pánico" },
    { id: "paro_motor_puerta", label: "Paro de motor de puerta" },
    { id: "paro_motor", label: "Paro de motor" },
    { id: "camaras", label: "Cámaras" },
    { id: "desactivar_puertas", label: "Desactivar puertas" },
  ];

  let selected = [];
  if (currentValue) {
    try {
      selected = JSON.parse(currentValue);
      if (!Array.isArray(selected)) selected = [];
    } catch (e) {
      selected = [];
    }
  }

  return opciones
    .map((opcion) => {
      const checked = selected.includes(opcion.id) ? "checked" : "";
      return `
                  <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-100 p-2 rounded">
                    <input
                      type="checkbox"
                      name="gpsValidacion"
                      value="${opcion.id}"
                      ${checked}
                      ${disabled}
                      class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                    />
                    <span class="text-sm text-gray-700">${opcion.label}</span>
                  </label>
                `;
    })
    .join("");
}

function getGpsValidacionValues() {
  const checkboxes = document.querySelectorAll(
    'input[name="gpsValidacion"]:checked',
  );
  const values = Array.from(checkboxes).map((cb) => cb.value);
  return JSON.stringify(values);
}

function formatGpsEstado(gpsValidacionEstado) {
  if (!gpsValidacionEstado) return "Sin registro";

  try {
    const items = JSON.parse(gpsValidacionEstado);
    if (!Array.isArray(items) || items.length === 0) return "Sin validación";

    const labels = {
      boton_panico: "Botón de pánico",
      paro_motor_puerta: "Paro de motor de puerta",
      paro_motor: "Paro de motor",
      camaras: "Cámaras",
      desactivar_puertas: "Desactivar puertas",
    };

    return items.map((item) => labels[item] || item).join(", ");
  } catch (e) {
    return String(gpsValidacionEstado);
  }
}

function isGpsOperativo(gpsValidacionEstado) {
  if (!gpsValidacionEstado) return false;

  try {
    const items = JSON.parse(gpsValidacionEstado);
    return Array.isArray(items) && items.length > 0;
  } catch (e) {
    return String(gpsValidacionEstado).toLowerCase() === "operativo";
  }
}
function updateTimeFieldStatus(fieldName) {
  const input = document.querySelector(`input[name="${fieldName}"]`);
  const container = document.getElementById(`container-${fieldName}`);
  const badge = document.getElementById(`badge-${fieldName}`);

  if (!input || !container) return;

  const hasValue = input.value && input.value.trim();

  if (hasValue) {
    container.className = "field-programada p-4 transition-all duration-300";
    if (badge) badge.style.display = "none";
  } else {
    container.className = "field-no-programada p-4 transition-all duration-300";
    if (badge) badge.style.display = "inline-flex";
  }
}

function renderEstatusBadge(estatus) {
  const badges = {
    Programado:
      '<span class="badge-estatus badge-programado">⚙️ Programado</span>',
    "En ruta":
      '<span class="badge-estatus badge-en-ruta">🚛 En Tránsito</span>',
    "Despacho realizado":
      '<span class="badge-estatus badge-realizado">✅ Entregado Exitosamente</span>',
    "Despacho No realizado":
      '<span class="badge-estatus badge-no-realizado">❌ No Entregado</span>',
    Cancelado:
      '<span class="badge-estatus badge-cancelado">🚫 Cancelado</span>',
  };
  return badges[estatus] || "";
}

function updateEstatusDisplay(estatus) {
  const container = document.getElementById("estatus-badge-container");
  if (container) {
    container.innerHTML = renderEstatusBadge(estatus);
  }
}

function showGpsDetailsModal(index) {
  const d = filteredDespachosData[index];
  if (!d) return;

  const titleEl = document.getElementById("gps-details-modal-title");
  const bodyEl = document.getElementById("gps-details-modal-body");

  titleEl.textContent = `Validación GPS - ${d.unidad}`;

  const gpsOk = isGpsOperativo(d.gpsValidacionEstado);
  const statusClass = gpsOk
    ? "bg-green-100 text-green-800"
    : "bg-red-100 text-red-800";
  const statusText = gpsOk ? "Validado" : "Sin validación";

  let validacionesHtml =
    '<p class="text-gray-500 italic">Sin validaciones registradas</p>';

  if (gpsOk) {
    try {
      const items = JSON.parse(d.gpsValidacionEstado);
      const labels = {
        boton_panico: "Botón de pánico",
        paro_motor_puerta: "Paro de motor de puerta",
        paro_motor: "Paro de motor",
        camaras: "Cámaras",
        desactivar_puertas: "Desactivar puertas",
      };

      validacionesHtml = '<ul class="space-y-2">';
      items.forEach((item) => {
        validacionesHtml += `
                      <li class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-gray-700">${escapeHtml(labels[item] || item)}</span>
                      </li>
                    `;
      });
      validacionesHtml += "</ul>";
    } catch (e) {
      validacionesHtml = `<p class="text-gray-700">${escapeHtml(d.gpsValidacionEstado)}</p>`;
    }
  }

  bodyEl.innerHTML = `
                <div class="space-y-4">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-600">Estado:</span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold ${statusClass}">
                      ${statusText}
                    </span>
                  </div>

                  <div class="border-t pt-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Accesorios Validados:</h4>
                    ${validacionesHtml}
                  </div>

                  <div class="border-t pt-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                      <div>
                        <span class="font-medium text-gray-600">Cliente:</span>
                        <p class="text-gray-800">${escapeHtml(d.cliente || "N/A")}</p>
                      </div>
                      <div>
                        <span class="font-medium text-gray-600">Placas:</span>
                        <p class="text-gray-800">${escapeHtml(d.placas || "N/A")}</p>
                      </div>
                      <div>
                        <span class="font-medium text-gray-600">Operador Monitor:</span>
                        <p class="text-gray-800">${escapeHtml(d.operadorMonitoreoId || "No asignado")}</p>
                      </div>
                      <div>
                        <span class="font-medium text-gray-600">Última actualización:</span>
                        <p class="text-gray-800">${d.gpsValidacionTimestamp ? formatDateTime(d.gpsValidacionTimestamp) : "Sin registro"}</p>
                      </div>
                    </div>
                  </div>
                </div>
              `;

  openModal("gps-details-modal");
}

function addIncidencia(index) {
  if (userRole !== "editor" && userRole !== "admin") return;

  const sel = document.getElementById("incidencia-tipo");
  const tipo = sel ? sel.value : "";
  if (!tipo) return alert("Seleccione una incidencia.");

  const dirInput = document.getElementById("incidencia-direccion");

  const direccion = (dirInput?.value || "").trim();

  if (!direccion)
    return alert("Ingrese la dirección de ocurrencia de la incidencia.");

  const d = filteredDespachosData[index];
  if (!d) return;

  d.incidencias = Array.isArray(d.incidencias) ? d.incidencias : [];

  const incObj = {
    tipo,
    fecha: new Date().toISOString(),
    severidad: getIncidenciaSeveridad(tipo),
    direccion,
  };

  d.incidencias.push(incObj);

  saveAllData();

  const el = document.getElementById("incidencias-list");
  if (el) el.innerHTML = renderIncidencias(d.incidencias, index, true);

  updateGuardarButtonState(index);

  renderRegistroDespacho();
  renderIncidenciasExecutiveReport();

  try {
    if (sel) sel.value = "";

    if (dirInput) dirInput.value = "";

    handleIncidenciaTipoChange();
  } catch (e) {}

  try {
    const datosIncidencia = {
      titulo: `Incidencia ${incObj.severidad}: ${tipo} | Unidad ${
        d.unidad
      } | Folio ${String(d.folio).padStart(3, "0")}`,
      cuerpo: [
        `Unidad: ${d.unidad || "N/A"}`,
        `Folio: ${String(d.folio || "")}`,
        `Fecha programada: ${d.fechaProgramada || ""}`,
        `Operador: ${d.operador || "N/A"}`,
        `Teléfono: ${d.telefono || "N/A"}`,
        `Ruta: ${d.ruta || "N/A"}`,
        `Origen: ${d.origen || "N/A"}`,
        `Destino: ${d.destino || "N/A"}`,
        `Severidad: ${incObj.severidad}`,
        `Estatus: ${d.estatus || "Programado"}`,
        `Dirección: ${incObj.direccion || "N/A"}`,
      ].join("\n"),
      direccion: incObj.direccion || "",
      unidad: d.unidad || "",
      folio: String(d.folio || ""),
      operador: d.operador || "",
      telefono: d.telefono || "",
      ruta: d.ruta || "",
      origen: d.origen || "",
      destino: d.destino || "",
      severidad: incObj.severidad || "",
      tipoIncidencia: tipo || "",
    };
    enviarIncidencia(datosIncidencia);
  } catch (e) {
    console.warn("No se pudo preparar/enviar la incidencia a Make:", e);
  }
}

let pendingSaveIndex = null;

function saveSeguimiento(index) {
  if (userRole !== "editor" && userRole !== "admin") return;

  const d = filteredDespachosData[index];

  const hasIncidencias =
    Array.isArray(d?.incidencias) && d.incidencias.length > 0;
  if (!hasIncidencias) {
    pendingSaveIndex = index;
    document.getElementById("confirm-save-modal").classList.remove("hidden");
    return;
  }

  executeSaveSeguimiento(index);
}

function closeConfirmSaveModal(confirmed) {
  document.getElementById("confirm-save-modal").classList.add("hidden");
  if (confirmed && pendingSaveIndex !== null) {
    executeSaveSeguimiento(pendingSaveIndex);
  }
  pendingSaveIndex = null;
}

function executeSaveSeguimiento(index) {
  const d = filteredDespachosData[index];
  const form = document.getElementById("form-seguimiento");

  d.operadorMonitoreoId = form.elements["operadorMonitoreoId"]?.value || "";

  const gpsValuesNew = getGpsValidacionValues();
  const gpsValuesChanged = gpsValuesNew !== d.gpsValidacionEstado;
  d.gpsValidacionEstado = gpsValuesNew;

  if (gpsValuesChanged && gpsValuesNew !== "[]") {
    d.gpsValidacionTimestamp = new Date().toISOString();
  }

  d.realSalidaUnidad = form.elements["realSalidaUnidad"]?.value || "";
  d.realCarga = form.elements["realCarga"]?.value || "";
  d.realSalida = form.elements["realSalida"]?.value || "";
  d.realDescarga = form.elements["realDescarga"]?.value || "";

  d.citaSalidaUnidad =
    form.elements["citaSalidaUnidad"]?.value || d.citaSalidaUnidad || "";
  d.citaCarga = form.elements["citaCarga"]?.value || d.citaCarga || "";
  d.citaSalida = form.elements["citaSalida"]?.value || d.citaSalida || "";
  d.citaDescarga = form.elements["citaDescarga"]?.value || d.citaDescarga || "";

  d.estatus_especial = form.elements["estatus_especial"]?.value || "";

  d.estatus = calcularEstatusAutomatico(d);

  d.observaciones = form.elements["observaciones"]?.value || "";

  saveAllData();
  renderAllTabs();
  _syncUnidadSelectorByIndex(index);
  renderSeguimientoForm(index);

  saveSeguimientoToDb(filteredDespachosData[index]).catch((err) => {
    console.error("Error guardando seguimiento en BD", err);
  });
}

const SEGUIMIENTO_SAVE_ENDPOINT =
  "/bitacora_/src/seguimiento/guardar_seguimiento.php";
const SEGUIMIENTO_LOAD_ENDPOINT =
  "/bitacora_/src/seguimiento/obtener_seguimiento.php";

let seguimientosDbCache = {
  loaded: false,
  map: new Map(),
};

let origenDestinoDbCache = {
  loaded: false,
  map: new Map(),
};

function applyOrigenDestinoToArray(arr) {
  if (!origenDestinoDbCache.loaded || !arr?.length) return;
  arr.forEach((d) => {
    const key = `${String(d.folio ?? "").trim()}|${String(d.unidad ?? "").trim()}|${String(d.fechaProgramada ?? "").trim()}`;
    const ov = origenDestinoDbCache.map.get(key);
    if (!ov) return;
    if (ov.origen) d.origen = ov.origen;
    if (ov.destino) d.destino = ov.destino;
  });
}

async function loadOrigenDestinoFromDb() {
  const res = await fetch(
    `/bitacora_/src/origen_destino/obtener_origen_destino.php?_=${Date.now()}`,
    { cache: "no-store" },
  );
  let json = null;
  try {
    json = await res.clone().json();
  } catch (_) {
    json = null;
  }
  if (!res.ok) {
    const msg = json?.message ? String(json.message) : `HTTP ${res.status}`;
    throw new Error(msg);
  }
  if (!json) json = await res.json();
  const rows = Array.isArray(json?.data) ? json.data : [];
  const m = new Map();
  rows.forEach((r) => {
    const key = `${String(r.folio ?? "").trim()}|${String(r.unidad ?? "").trim()}|${String(r.fecha_programada ?? "").trim()}`;
    m.set(key, {
      folio: r.folio ?? "",
      unidad: r.unidad ?? "",
      fechaProgramada: r.fecha_programada ?? "",
      origen: r.origen ?? "",
      destino: r.destino ?? "",
    });
  });
  origenDestinoDbCache.map = m;
  origenDestinoDbCache.loaded = true;
  applyOrigenDestinoToArray(allDespachosData);
  applyOrigenDestinoToArray(filteredDespachosData);
}

function makeSeguimientoKey(folio, unidad, fechaProgramada) {
  return `${String(folio ?? "").trim()}|${String(
    unidad ?? "",
  ).trim()}|${String(fechaProgramada ?? "").trim()}`;
}

function parseIncidenciasFromDbString(raw) {
  const s = (raw ?? "").toString().trim();
  if (!s) return [];
  return s
    .split(";;")
    .map((part) => part.trim())
    .filter(Boolean)
    .map((part) => {
      const pieces = part.split(" | ");
      const tipo = (pieces[0] ?? "").trim();
      const severidad = (pieces[1] ?? "").trim();
      const fecha = (pieces[2] ?? "").trim();
      const direccion = (pieces[3] ?? "").trim();
      return { tipo, severidad, fecha, direccion };
    })
    .filter((x) => x.tipo);
}

function normalizeSeguimientoDbRow(r) {
  const folio = r?.folio ?? "";
  const unidad = r?.unidad ?? "";
  const fechaProgramada = r?.fecha_programada ?? "";
  return {
    key: makeSeguimientoKey(folio, unidad, fechaProgramada),
    folio,
    unidad,
    fechaProgramada,

    operadorMonitoreoId: r?.operador_monitoreo ?? "",
    gpsValidacionEstado: r?.gps_estado ?? "",
    gpsValidacionTimestamp: r?.gps_timestamp ?? "",

    realSalidaUnidad: r?.real_salida_unidad ?? "",
    realCarga: r?.real_carga ?? "",
    realSalida: r?.real_salida ?? "",
    realDescarga: r?.real_descarga ?? "",
    citaSalidaUnidad: r?.cita_salida_unidad ?? "",
    citaCarga: r?.cita_carga ?? "",
    citaSalida: r?.cita_salida ?? "",
    citaDescarga: r?.cita_descarga ?? "",
    estatus: r?.estatus ?? "Programado",
    estatus_especial: r?.estatus_especial ?? "",
    observaciones: r?.observaciones ?? "",
    incidencias: parseIncidenciasFromDbString(r?.incidencias),
  };
}

function applySeguimientosDbToArray(arr) {
  if (!seguimientosDbCache.loaded || !arr?.length) return;
  arr.forEach((d) => {
    const key = makeSeguimientoKey(d.folio, d.unidad, d.fechaProgramada);
    const s = seguimientosDbCache.map.get(key);
    if (!s) return;
    d.operadorMonitoreoId = s.operadorMonitoreoId || d.operadorMonitoreoId;
    d.gpsValidacionEstado = s.gpsValidacionEstado || d.gpsValidacionEstado;
    d.gpsValidacionTimestamp =
      s.gpsValidacionTimestamp || d.gpsValidacionTimestamp;

    d.realSalidaUnidad = s.realSalidaUnidad || d.realSalidaUnidad;
    d.realCarga = s.realCarga || d.realCarga;
    d.realSalida = s.realSalida || d.realSalida;
    d.realDescarga = s.realDescarga || d.realDescarga;
    if (s.citaSalidaUnidad) d.citaSalidaUnidad = s.citaSalidaUnidad;
    if (s.citaCarga) d.citaCarga = s.citaCarga;
    if (s.citaSalida) d.citaSalida = s.citaSalida;
    if (s.citaDescarga) d.citaDescarga = s.citaDescarga;
    d.estatus = s.estatus || d.estatus;
    d.estatus_especial = s.estatus_especial || d.estatus_especial || "";
    d.observaciones =
      typeof s.observaciones === "string" && s.observaciones.trim()
        ? s.observaciones
        : d.observaciones;

    if (Array.isArray(s.incidencias) && s.incidencias.length)
      d.incidencias = s.incidencias;
  });
}

async function loadSeguimientosFromDb() {
  const res = await fetch(`${SEGUIMIENTO_LOAD_ENDPOINT}?_=${Date.now()}`, {
    cache: "no-store",
  });

  let json = null;
  try {
    json = await res.clone().json();
  } catch (_) {
    json = null;
  }

  if (!res.ok) {
    const msg = json?.message ? String(json.message) : `HTTP ${res.status}`;
    throw new Error(msg);
  }

  if (!json) json = await res.json();
  const rows = Array.isArray(json?.data)
    ? json.data
    : Array.isArray(json)
      ? json
      : [];

  const m = new Map();
  rows.forEach((r) => {
    const s = normalizeSeguimientoDbRow(r);
    if (s.key) m.set(s.key, s);
  });

  seguimientosDbCache.map = m;
  seguimientosDbCache.loaded = true;
  applySeguimientosDbToArray(allDespachosData);
  applySeguimientosDbToArray(filteredDespachosData);
}

async function saveSeguimientoToDb(d) {
  const payload = {
    folio: d.folio,
    unidad: d.unidad,
    fechaProgramada: d.fechaProgramada,
    operadorMonitoreoId: d.operadorMonitoreoId || "",
    gpsValidacionEstado: d.gpsValidacionEstado || "",
    gpsValidacionTimestamp: d.gpsValidacionTimestamp || "",
    realSalidaUnidad: d.realSalidaUnidad || "",
    realCarga: d.realCarga || "",
    realSalida: d.realSalida || "",
    realDescarga: d.realDescarga || "",
    citaSalidaUnidad: d.citaSalidaUnidad || "",
    citaCarga: d.citaCarga || "",
    citaSalida: d.citaSalida || "",
    citaDescarga: d.citaDescarga || "",
    estatus: d.estatus || "Programado",
    estatus_especial: d.estatus_especial || "",
    observaciones: d.observaciones || "",
    incidencias: Array.isArray(d.incidencias) ? d.incidencias : [],
  };

  const res = await fetch(`${SEGUIMIENTO_SAVE_ENDPOINT}?_=${Date.now()}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });

  let json = null;
  try {
    json = await res.clone().json();
  } catch (_) {
    json = null;
  }

  if (!res.ok || json?.success !== true) {
    const msg = json?.message ? String(json.message) : `HTTP ${res.status}`;
    throw new Error(msg);
  }

  const key = makeSeguimientoKey(d.folio, d.unidad, d.fechaProgramada);
  seguimientosDbCache.map.set(key, {
    key,
    folio: d.folio,
    unidad: d.unidad,
    fechaProgramada: d.fechaProgramada,
    operadorMonitoreoId: d.operadorMonitoreoId || "",
    gpsValidacionEstado: d.gpsValidacionEstado || "",
    gpsValidacionTimestamp: d.gpsValidacionTimestamp || "",
    realSalidaUnidad: d.realSalidaUnidad || "",
    realCarga: d.realCarga || "",
    realSalida: d.realSalida || "",
    realDescarga: d.realDescarga || "",
    citaSalidaUnidad: d.citaSalidaUnidad || "",
    citaCarga: d.citaCarga || "",
    citaSalida: d.citaSalida || "",
    citaDescarga: d.citaDescarga || "",
    estatus: d.estatus || "Programado",
    estatus_especial: d.estatus_especial || "",
    observaciones: d.observaciones || "",
    incidencias: Array.isArray(d.incidencias) ? d.incidencias : [],
  });
  seguimientosDbCache.loaded = true;

  renderRegistroDespacho();
  renderKPIs(filteredDespachosData);
  renderIncidenciasExecutiveReport();

  return json;
}

function populateRegistroFilter() {
  const selEstatus = document.getElementById("registro-unidad-filter");
  if (selEstatus) {
    selEstatus.innerHTML = '<option value="all">Todos los estatus</option>';

    const estatusSet = new Set();
    (filteredDespachosData || []).forEach((d) => {
      const est = String(d.estatus || "Sin estatus").trim();
      estatusSet.add(est);
    });

    const ordenEstatus = [
      "Programado",
      "En ruta",
      "Despacho realizado",
      "Despacho No realizado",
      "Cancelado",
      "Sin estatus",
    ];

    const estatusOrdenados = ordenEstatus.filter((e) => estatusSet.has(e));

    estatusSet.forEach((e) => {
      if (!ordenEstatus.includes(e)) {
        estatusOrdenados.push(e);
      }
    });

    estatusOrdenados.forEach((e) => {
      selEstatus.innerHTML += `<option value="${escapeHtml(
        e,
      )}">${escapeHtml(e)}</option>`;
    });
  }

  const selUnidad = document.getElementById("registro-unidades-filter");
  if (selUnidad) {
    selUnidad.innerHTML = '<option value="all">Todas las unidades</option>';

    const unidadesSet = new Set();
    (filteredDespachosData || []).forEach((d) => {
      const unidad = String(d.unidad || "").trim();
      if (unidad) unidadesSet.add(unidad);
    });

    const unidadesOrdenadas = Array.from(unidadesSet).sort((a, b) =>
      a.localeCompare(b, "es", { sensitivity: "base" }),
    );

    unidadesOrdenadas.forEach((u) => {
      selUnidad.innerHTML += `<option value="${escapeHtml(
        u,
      )}">${escapeHtml(u)}</option>`;
    });
  }
}

function renderRegistroDespacho() {
  const container = document.getElementById("registro-board-container");
  const filterEstatus = document.getElementById("registro-unidad-filter");
  const filterUnidad = document.getElementById("registro-unidades-filter");
  container.innerHTML = "";

  let data = filteredDespachosData || [];

  if (filterEstatus && filterEstatus.value !== "all") {
    data = data.filter(
      (d) =>
        String(d.estatus || "").trim() ===
        String(filterEstatus.value || "").trim(),
    );
  }

  if (filterUnidad && filterUnidad.value !== "all") {
    data = data.filter(
      (d) =>
        String(d.unidad || "").trim() ===
        String(filterUnidad.value || "").trim(),
    );
  }

  if (!data.length) {
    container.innerHTML = `<div class="text-center p-6">No hay despachos para mostrar.</div>`;
    return;
  }

  const unidadCount = {};
  const unidadIndex = {};
  data.forEach((d) => {
    const unidadNombre = String(d.unidad || "").trim();
    unidadCount[unidadNombre] = (unidadCount[unidadNombre] || 0) + 1;
    unidadIndex[unidadNombre] = 0;
  });

  const board = document.createElement("div");
  board.className = "registro-board";

  const header = document.createElement("div");
  header.className = "board-row";
  [
    "Unidad",
    "Salida inicial de la Unidad",
    "Proceso de Carga",
    "Salida de carga",
    "Proceso de Descarga",
    "Estatus",
  ].forEach((h) => {
    const cell = document.createElement("div");
    cell.className = "board-cell";
    cell.textContent = h;
    cell.setAttribute("data-label", h);
    header.appendChild(cell);
  });
  board.appendChild(header);

  data.forEach((d) => {
    const row = document.createElement("div");
    row.className = "board-row";

    const realIndex = filteredDespachosData.findIndex(
      (item) =>
        item.folio === d.folio &&
        item.unidad === d.unidad &&
        item.fechaProgramada === d.fechaProgramada,
    );

    const unidadNombre = String(d.unidad || "").trim();
    const tieneMultiplesTramos = unidadCount[unidadNombre] > 1;
    unidadIndex[unidadNombre] = (unidadIndex[unidadNombre] || 0) + 1;
    const numeroTramo = unidadIndex[unidadNombre];
    const tramoLabel = tieneMultiplesTramos ? ` - Tramo ${numeroTramo}` : "";

    const gpsOk = isGpsOperativo(d.gpsValidacionEstado);
    const dotClass = gpsOk ? "bg-green-500" : "bg-red-500";

    const uc = document.createElement("div");
    uc.className = "board-cell unit-cell";
    uc.setAttribute("data-label", "Unidad");
    uc.innerHTML = `
                  <div class="flex items-center justify-center gap-2">
                    <span class="inline-block w-3 h-3 rounded-full ${dotClass}" title="${gpsOk ? "GPS validado" : "Sin validación GPS"}"></span>
                    <strong>${escapeHtml(d.unidad)}${tramoLabel}</strong>
                    <button
                      onclick="showGpsDetailsModal(${realIndex})"
                      class="ml-2 text-indigo-600 hover:text-indigo-800 transition"
                      title="Ver detalles de validación GPS"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      </svg>
                    </button>
                  </div>
                  <p class="text-xs">${escapeHtml(d.placas)}</p>
                  <p class="text-xs italic">${
                    d.operadorMonitoreoId
                      ? "Operador: " + escapeHtml(d.operadorMonitoreoId)
                      : ""
                  }</p>
              `;
    row.appendChild(uc);

    const tasks = [
      {
        label: "Salida inicial de la Unidad",
        cita: d.citaSalidaUnidad,
        real: d.realSalidaUnidad,
      },
      { label: "Proceso de Carga", cita: d.citaCarga, real: d.realCarga },
      {
        label: "Salida de carga",
        cita: d.citaSalida,
        real: d.realSalida,
      },
      {
        label: "Proceso de Descarga",
        cita: d.citaDescarga,
        real: d.realDescarga,
      },
    ];
    tasks.forEach((t) => {
      const cell = document.createElement("div");
      const cls = t.real ? checkTimeDeviation(t.cita, t.real) : "task-cell";
      cell.className = `board-cell ${cls}`;
      cell.setAttribute("data-label", t.label);
      cell.innerHTML = `<p class="text-xs">Prog: ${formatDateTime(
        t.cita,
      )}</p><p class="font-semibold">Real: ${
        t.real ? formatDateTime(t.real) : "-"
      }</p>`;
      row.appendChild(cell);
    });

    const statusCell = document.createElement("div");

    const estatusCalculado = calcularEstatusAutomatico(d);

    let stClass = "task-cell";
    if (estatusCalculado === "En ruta") stClass = "estatus-en-ruta";
    else if (estatusCalculado === "Cargando") stClass = "estatus-cargando";
    else if (estatusCalculado === "Salida de Carga")
      stClass = "estatus-salida-carga";
    else if (estatusCalculado === "Despacho realizado")
      stClass = "estatus-verde";
    else if (estatusCalculado === "Despacho No realizado")
      stClass = "estatus-rojo";
    else if (estatusCalculado === "Cancelado") stClass = "estatus-cancelado";

    statusCell.className = `board-cell ${stClass}`;
    statusCell.setAttribute("data-label", "Estatus");
    statusCell.textContent = estatusCalculado || "Programado";
    row.appendChild(statusCell);

    board.appendChild(row);
  });

  container.appendChild(board);
}

function renderKPIs(data) {
  const total = data.length;

  const dataConEstatus = data.map((d) => ({
    ...d,
    estatusCalculado: calcularEstatusAutomatico(d),
  }));

  const realizados = dataConEstatus.filter(
    (d) => d.estatusCalculado === "Despacho realizado",
  );
  const enRuta = dataConEstatus.filter((d) => d.estatusCalculado === "En ruta");
  const cargando = dataConEstatus.filter(
    (d) => d.estatusCalculado === "Cargando",
  );
  const salidaCarga = dataConEstatus.filter(
    (d) => d.estatusCalculado === "Salida de Carga",
  );
  const programados = dataConEstatus.filter(
    (d) => d.estatusCalculado === "Programado",
  );
  const cancelados = dataConEstatus.filter(
    (d) => d.estatusCalculado === "Cancelado",
  );
  const noRealizados = dataConEstatus.filter(
    (d) => d.estatusCalculado === "Despacho No realizado",
  );

  const aTiempo = realizados.filter(
    (d) =>
      d.realDescarga &&
      checkTimeDeviation(d.citaDescarga, d.realDescarga) === "estatus-verde",
  );
  const conRetraso = realizados.length - aTiempo.length;

  const enProceso = enRuta.length + cargando.length + salidaCarga.length;

  document.getElementById("kpi-total-despachos").textContent = total;
  document.getElementById("kpi-despachos-a-tiempo").textContent =
    aTiempo.length;
  document.getElementById("kpi-despachos-retraso").textContent = conRetraso;
  document.getElementById("kpi-despachos-en-ruta").textContent = enProceso;
  document.getElementById("kpi-despachos-programados").textContent =
    programados.length;
  document.getElementById("kpi-despachos-cancelados").textContent =
    cancelados.length + noRealizados.length;

  const incCount = {};
  data.forEach((d) =>
    (d.incidencias || []).forEach((i) => {
      incCount[i.tipo] = (incCount[i.tipo] || 0) + 1;
    }),
  );
  updateCumplimientoChart([
    aTiempo.length,
    conRetraso,
    enProceso,
    programados.length,
    cancelados.length + noRealizados.length,
  ]);
  updateIncidenciasChart(incCount);
}

function updateCumplimientoChart(vals) {
  const ctx = document
    .getElementById("kpi-cumplimiento-chart")
    .getContext("2d");
  if (charts.cumplimiento) charts.cumplimiento.destroy();
  charts.cumplimiento = new Chart(ctx, {
    type: "bar",
    data: {
      labels: [
        "A Tiempo",
        "Con Retraso",
        "En Proceso",
        "Programados",
        "Cancelados",
      ],
      datasets: [
        {
          data: vals,
          backgroundColor: [
            "#16a34a",
            "#dc2626",
            "#ca8a04",
            "#2563eb",
            "#6090fa",
          ],
          borderRadius: 4,
          barThickness: 30,
        },
      ],
    },
    options: {
      indexAxis: "y",
      plugins: {
        legend: { display: false },
        datalabels: {
          anchor: "end",
          align: "right",
          offset: 8,
          color: "#111827",
          font: { size: 12, weight: "700" },
        },
      },
      responsive: true,
    },
  });
}

function updateIncidenciasChart(map) {
  const ctx = document.getElementById("kpi-incidencias-chart").getContext("2d");
  if (charts.incidencias) charts.incidencias.destroy();
  const labels = Object.keys(map);
  const values = Object.values(map);
  if (!labels.length) {
    charts.incidencias = new Chart(ctx, {
      type: "doughnut",
      data: { labels: ["Sin incidencias"], datasets: [{ data: [1] }] },
      options: { plugins: { legend: { display: false } } },
    });
    return;
  }
  charts.incidencias = new Chart(ctx, {
    type: "doughnut",
    data: { labels, datasets: [{ data: values, borderWidth: 3 }] },
    options: { plugins: { legend: { position: "bottom" } } },
  });
}

function renderInforme(data) {
  const c = document.getElementById("informe-container");
  const btn = document.getElementById("download-excel-report-btn");

  const window = getIncExecutiveDateWindow();
  const from = window.from || "";
  const to = window.to || "";

  let filteredData = data.slice();
  if (from && to) {
    filteredData = filteredData.filter(
      (d) => d.fechaProgramada >= from && d.fechaProgramada <= to,
    );
  } else if (from && !to) {
    filteredData = filteredData.filter((d) => d.fechaProgramada >= from);
  } else if (!from && to) {
    filteredData = filteredData.filter((d) => d.fechaProgramada <= to);
  }

  if (!filteredData.length) {
    c.innerHTML = `<p class="text-center p-6 bg-gray-100 rounded-lg">No hay datos para generar informe con el filtro seleccionado.</p>`;
    btn.disabled = true;
    lastInformeData = [];
    return;
  }
  lastInformeData = filteredData.slice();
  btn.disabled = false;

  const total = filteredData.length;
  const incs = filteredData.reduce(
    (a, d) => a + (d.incidencias || []).length,
    0,
  );
  c.innerHTML = `
                <div class="bg-white border rounded-xl p-4">
                  <p class="text-gray-800 font-semibold">Resumen</p>
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
                    <div class="bg-gray-50 border rounded-lg p-3"><p class="text-xs text-gray-500">Total</p><p class="text-2xl font-bold">${total}</p></div>
                    <div class="bg-yellow-50 border rounded-lg p-3"><p class="text-xs text-gray-500">Incidencias</p><p class="text-2xl font-bold">${incs}</p></div>
                    <div class="bg-green-50 border rounded-lg p-3"><p class="text-xs text-gray-500">Realizados</p><p class="text-2xl font-bold">${
                      filteredData.filter(
                        (d) => d.estatus === "Despacho realizado",
                      ).length
                    }</p></div>
                    <div class="bg-blue-50 border rounded-lg p-3"><p class="text-xs text-gray-500">En Ruta</p><p class="text-2xl font-bold">${
                      filteredData.filter((d) => d.estatus === "En ruta").length
                    }</p></div>
                  </div>
                </div>

                <div class="bg-white border rounded-xl p-4 pt-2">
                  <h3 class="text-lg font-semibold pl-4">Detalle</h3>
                  <div class="overflow-x-auto">
                  <table class="min-w-full text-sm border rounded-lg overflow-hidden">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-2 text-left">Unidad</th>
                        <th class="px-4 py-2 text-left">Ruta</th>
                        <th class="px-4 py-2 text-left">Estatus</th>
                        <th class="px-4 py-2 text-left">Prog. Descarga</th>
                        <th class="px-4 py-2 text-left">Real Descarga</th>
                        <th class="px-4 py-2 text-left min-w-[200px]">Observaciones</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y bg-white">
                      ${filteredData
                        .map(
                          (d) => `
                        <tr>
                          <td class="px-4 py-2 font-semibold">${escapeHtml(
                            d.unidad,
                          )}</td>
                          <td class="px-4 py-2">${escapeHtml(d.ruta)}</td>
                          <td class="px-4 py-2">${escapeHtml(d.estatus)}</td>
                          <td class="px-4 py-2">${formatDateTime(d.citaDescarga)}</td>
                          <td class="px-4 py-2 font-semibold">${formatDateTime(
                            d.realDescarga,
                          )}</td>
                          <td class="px-4 py-2 text-gray-600 text-xs">${escapeHtml(
                            d.observaciones || "-",
                          )}</td>
                        </tr>
                      `,
                        )
                        .join("")}
                    </tbody>
                  </table>
                  </div>
                </div>
                `;
}

function downloadReportAsExcel() {
  if (!lastInformeData.length) return alert("No hay datos para exportar.");
  const rows = lastInformeData.map((d) => ({
    Fecha: d.fechaProgramada
      ? dayjs(d.fechaProgramada).format("DD/MM/YYYY")
      : "",
    Folio: d.folio,
    Unidad: d.unidad,
    Placas: d.placas,
    Operador: d.operador,
    Telefono: d.telefono,
    Ruta: d.ruta,
    Origen: d.origen,
    Destino: d.destino,
    Estatus: d.estatus,
    "Prog Descarga": formatDateTime(d.citaDescarga),
    "Real Descarga": formatDateTime(d.realDescarga),
    Observaciones: d.observaciones || "",
    Incidencias: (d.incidencias || [])
      .map((i) => `${i.tipo} (${formatDateTime(i.fecha)})`)
      .join("; "),
    "Direccion de Incidencias": (d.incidencias || [])
      .map((i) => i.direccion || "N/A")
      .join("; "),
  }));
  const ws = XLSX.utils.json_to_sheet(rows);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Informe");
  XLSX.writeFile(wb, `Informe_Detallado_${dayjs().format("YYYY-MM-DD")}.xlsx`);
}

function exportUpdatedSheet() {
  if (!allDespachosData.length) return alert("No hay datos para exportar.");
  const rows = allDespachosData.map((d) => ({
    Fecha: d.fechaProgramada
      ? dayjs(d.fechaProgramada).format("DD/MM/YYYY")
      : "",
    Folio: d.folio,
    Unidad: d.unidad,
    Placas: d.placas,
    Operador: d.operador,
    Telefono: d.telefono,
    Ruta: d.ruta,
    Origen: d.origen,
    Destino: d.destino,
    "Prog Salida Unidad": formatDateTime(d.citaSalidaUnidad),
    "Real Salida Unidad": formatDateTime(d.realSalidaUnidad),
    "Prog Carga": formatDateTime(d.citaCarga),
    "Real Carga": formatDateTime(d.realCarga),
    "Prog Salida Carga": formatDateTime(d.citaSalida),
    "Real Salida Carga": formatDateTime(d.realSalida),
    "Prog Descarga": formatDateTime(d.citaDescarga),
    "Real Descarga": formatDateTime(d.realDescarga),
    Estatus: d.estatus,
    Observaciones: d.observaciones || "",
    Incidencias: (d.incidencias || [])
      .map((i) => `${i.tipo} (${formatDateTime(i.fecha)})`)
      .join("; "),
    "Dirección de Incidencias": (d.incidencias || [])
      .map((i) => i.direccion || "N/A")
      .join("; "),
  }));
  const ws = XLSX.utils.json_to_sheet(rows);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Bitacora");
  XLSX.writeFile(
    wb,
    `Bitacora_Actualizada_${dayjs().format("YYYY-MM-DD")}.xlsx`,
  );
}

const EMBEDDED_911_DATA = [
  {
    estado: "CIUDAD DE MEXICO",
    municipio: "CIUDAD DE MEXICO",
    tel: "5556581111",
  },
  { estado: "ESTADO DE MEXICO", municipio: "TOLUCA", tel: "7222152865" },
  { estado: "JALISCO", municipio: "GUADALAJARA", tel: "3336681900" },
  { estado: "NUEVO LEON", municipio: "MONTERREY", tel: "8183457755" },
  { estado: "PUEBLA", municipio: "PUEBLA", tel: "2223094800" },
  { estado: "QUERETARO", municipio: "QUERETARO", tel: "4422115100" },
  { estado: "VERACRUZ", municipio: "XALAPA", tel: "2288419700" },
];

let emergency911Cache = {
  loaded: false,
  rows: [],
  filtered: [],
  states: [],
  pageSize: 100,
  currentLimit: 100,
};

const EMERGENCY_911_ENDPOINT =
  "/bitacora_/src/contactos/obtener_numeros_emergencia.php";

function normalizeEmergency911Row(r) {
  const estado = (r?.estado ?? "").toString().trim().toUpperCase();
  const municipio = (r?.municipio ?? "").toString().trim().toUpperCase();
  const tel = (r?.tel ?? "").toString().trim();
  return { estado, municipio, tel };
}

async function loadEmergency911RowsFromDb() {
  const url = `${EMERGENCY_911_ENDPOINT}?_=${Date.now()}`;
  const res = await fetch(url, { cache: "no-store" });

  let json = null;
  try {
    json = await res.clone().json();
  } catch (_) {
    json = null;
  }

  if (!res.ok) {
    const msg = json?.message ? String(json.message) : `HTTP ${res.status}`;
    throw new Error(msg);
  }

  if (!json) json = await res.json();
  if (!json || json.success !== true)
    throw new Error(json?.message || "Respuesta no exitosa");
  const rows = Array.isArray(json.data) ? json.data : [];
  return rows
    .map(normalizeEmergency911Row)
    .filter((x) => x.estado || x.municipio || x.tel);
}

async function openEmergency911Modal() {
  const statusEl = document.getElementById("em911-status");
  const tbodyEl = document.getElementById("em911-tbody");

  const fallback =
    typeof EMBEDDED_911_DATA !== "undefined" && Array.isArray(EMBEDDED_911_DATA)
      ? EMBEDDED_911_DATA
      : [];

  if (!emergency911Cache.loaded) {
    if (statusEl) statusEl.textContent = "Cargando números de emergencia…";
    if (tbodyEl)
      tbodyEl.innerHTML =
        '<tr><td class="px-4 py-4 text-gray-500" colspan="3">Cargando…</td></tr>';

    try {
      const dbRows = await loadEmergency911RowsFromDb();
      emergency911Cache.rows = (
        dbRows.length ? dbRows : fallback.slice().map(normalizeEmergency911Row)
      ).sort((a, b) =>
        (a.estado + a.municipio).localeCompare(b.estado + b.municipio),
      );
    } catch (err) {
      console.warn(
        "No se pudo cargar números 911 desde BD, usando embebido.",
        err,
      );
      emergency911Cache.rows = fallback
        .slice()
        .map(normalizeEmergency911Row)
        .sort((a, b) =>
          (a.estado + a.municipio).localeCompare(b.estado + b.municipio),
        );
    }

    emergency911Cache.states = [
      ...new Set(emergency911Cache.rows.map((r) => r.estado)),
    ]
      .filter(Boolean)
      .sort((a, b) => a.localeCompare(b));

    const sel = document.getElementById("em911-estado");
    if (sel) {
      sel.innerHTML =
        `<option value="all">Todos los estados</option>` +
        emergency911Cache.states
          .map(
            (s) => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`,
          )
          .join("");
    }

    emergency911Cache.loaded = true;
  }

  applyEmergency911Filters();
  openModal("emergency911-modal");
}

function applyEmergency911Filters() {
  const estado = document.getElementById("em911-estado")?.value || "all";
  const term = (document.getElementById("em911-search")?.value || "")
    .trim()
    .toLowerCase();
  let f = emergency911Cache.rows;
  if (estado !== "all") f = f.filter((r) => r.estado === estado);
  if (term)
    f = f.filter((r) => (r.municipio || "").toLowerCase().includes(term));
  emergency911Cache.filtered = f;
  emergency911Cache.currentLimit = emergency911Cache.pageSize;
  renderEmergency911Table();
}

function loadMoreEmergency911Rows() {
  emergency911Cache.currentLimit = Math.min(
    emergency911Cache.currentLimit + emergency911Cache.pageSize,
    emergency911Cache.filtered.length,
  );
  renderEmergency911Table();
}

function renderEmergency911Table() {
  const total = emergency911Cache.filtered.length;
  const show = Math.min(emergency911Cache.currentLimit, total);
  document.getElementById("em911-status").textContent =
    `Mostrando ${show} de ${total} registros.`;
  const slice = emergency911Cache.filtered.slice(0, show);
  const tbody = document.getElementById("em911-tbody");
  tbody.innerHTML =
    slice
      .map(
        (r) => `
          <tr>
            <td class="px-4 py-2 whitespace-nowrap">${escapeHtml(r.estado)}</td>
            <td class="px-4 py-2">${escapeHtml(r.municipio)}</td>
            <td class="px-4 py-2 font-semibold">${escapeHtml(r.tel)}</td>
          </tr>
        `,
      )
      .join("") ||
    `<tr><td class="px-4 py-4 text-gray-500" colspan="3">Sin resultados.</td></tr>`;
}

let emergencyContactsCache = { loaded: false, items: [] };

function renderEmergencyContacts(items) {
  const container = document.getElementById("emergency-contacts-body");

  if (!items || items.length === 0) {
    container.innerHTML = `
            <div class="col-span-full text-center p-10 bg-orange-50 border-2 border-orange-200 rounded-xl">
              <p class="text-lg font-medium text-orange-800 mb-2">No hay contactos disponibles</p>
              <p class="text-sm text-orange-600">Verifica la conexión con Google Sheets o añade datos en la pestaña CONTACTOS_EMERGENCIA</p>
            </div>
          `;
    return;
  }

  const html = items
    .map((contacto) => {
      const badgeClass = contacto.prioridad.toLowerCase().includes("critica")
        ? "bg-red-100 text-red-800 border-red-300"
        : contacto.prioridad.toLowerCase().includes("alta")
          ? "bg-orange-100 text-orange-800 border-orange-300"
          : "bg-green-100 text-green-800 border-green-300";

      const telefonosHtml = contacto.telefonos.length
        ? contacto.telefonos
            .map(
              (tel) =>
                `<a href="tel:${tel.replace(
                  /[\s-]/g,
                  "",
                )}" class="text-blue-600 hover:text-blue-800 font-medium">${tel}</a>`,
            )
            .join(", ")
        : "Sin teléfono";

      const correosHtml = contacto.correos.length
        ? contacto.correos
            .map(
              (email) =>
                `<a href="mailto:${email}" class="text-blue-600 hover:text-blue-800">${email}</a>`,
            )
            .join(", ")
        : "Sin correo";

      return `
            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-100 hover:shadow-xl transition-all">
              <div class="flex items-start justify-between mb-4">
                <div>
                  <h4 class="font-bold text-lg text-gray-900 mb-1">${escapeHtml(
                    contacto.nombre,
                  )}</h4>
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold ${badgeClass} border">
                    ${escapeHtml(contacto.prioridad)}
                  </span>
                </div>
                <div class="text-right">
                  <p class="text-sm font-medium text-gray-700">Cargo/Posicion: ${escapeHtml(
                    contacto.cargo,
                  )}</p>
                  <p class="text-xs text-gray-500">Departamento: ${escapeHtml(
                    contacto.departamento,
                  )}</p>
                </div>
              </div>

              <div class="space-y-2 text-sm mb-4">
                <div><strong>📞 Teléfonos:</strong> ${telefonosHtml}</div>
                <div><strong>✉️ Correos:</strong> ${correosHtml}</div>
                ${
                  contacto.acciones
                    ? `<div><strong>⚡ Acciones:</strong> ${escapeHtml(
                        contacto.acciones,
                      )}</div>`
                    : ""
                }
                ${
                  contacto.observaciones
                    ? `<div class="text-xs text-gray-600"><strong>📝 Observaciones:</strong> ${escapeHtml(
                        contacto.observaciones,
                      )}</div>`
                    : ""
                }
              </div>
            </div>
          `;
    })
    .join("");

  container.innerHTML = html;
}

async function openEmergencyContactsModal() {
  const container = document.getElementById("emergency-contacts-body");

  container.innerHTML = `
          <div class="col-span-full text-center p-10 bg-gray-50 rounded-xl">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500 mb-4"></div>
            <p class="text-gray-600">Cargando contactos de emergencia...</p>
          </div>
        `;

  openModal("emergency-contacts-modal");

  try {
    if (
      !emergencyContactsCache.loaded ||
      !emergencyContactsCache.items.length
    ) {
      const items = await loadEmergencyContactsFromSheet();

      if (items && items.length > 0) {
        emergencyContactsCache.items = items;
      } else {
        emergencyContactsCache.items = EMERGENCYCONTACTS.slice();
      }

      emergencyContactsCache.loaded = true;
    }

    renderEmergencyContacts(emergencyContactsCache.items);
  } catch (error) {
    console.error("Error cargando contactos:", error);
    // Fallback inmediato
    renderEmergencyContacts(EMERGENCYCONTACTS);
  }
}
let incExecutiveFilterMode = "selected";
let incExecutiveRange = { from: "", to: "" };

function setIncExecutiveFilter(mode) {
  incExecutiveFilterMode = mode;
  const all = ["selected", "today", "last7", "last30", "all"];
  all.forEach((m) => {
    const id =
      m === "last7"
        ? "incf-btn-7"
        : m === "last30"
          ? "incf-btn-30"
          : `incf-btn-${m}`;
    const btn = document.getElementById(id);
    if (!btn) return;
    const active = m === mode;
    btn.classList.toggle("bg-indigo-600", active);
    btn.classList.toggle("text-white", active);
    btn.classList.toggle("border-indigo-600", active);
    btn.classList.toggle("bg-white", !active);
    btn.classList.toggle("text-gray-700", !active);
  });
  renderInforme(filteredDespachosData);
  renderIncidenciasExecutiveReport();
}

function applyIncExecutiveRange() {
  incExecutiveFilterMode = "range";
  incExecutiveRange.from = document.getElementById("incf-from")?.value || "";
  incExecutiveRange.to = document.getElementById("incf-to")?.value || "";
  [
    "incf-btn-selected",
    "incf-btn-today",
    "incf-btn-7",
    "incf-btn-30",
    "incf-btn-all",
  ].forEach((id) => {
    const btn = document.getElementById(id);
    if (!btn) return;
    btn.classList.remove("bg-indigo-600", "text-white", "border-indigo-600");
    btn.classList.add("bg-white", "text-gray-700");
  });
  renderInforme(filteredDespachosData);
  renderIncidenciasExecutiveReport();
}

function getIncExecutiveDateWindow() {
  const selected = document.getElementById("date-filter")?.value || "";
  const today = dayjs().format("YYYY-MM-DD");

  if (incExecutiveFilterMode === "all") return { from: "", to: "" };
  if (incExecutiveFilterMode === "selected")
    return { from: selected, to: selected };
  if (incExecutiveFilterMode === "today") return { from: today, to: today };
  if (incExecutiveFilterMode === "last7")
    return {
      from: dayjs().subtract(6, "day").format("YYYY-MM-DD"),
      to: today,
    };
  if (incExecutiveFilterMode === "last30")
    return {
      from: dayjs().subtract(29, "day").format("YYYY-MM-DD"),
      to: today,
    };
  if (incExecutiveFilterMode === "range")
    return { from: incExecutiveRange.from, to: incExecutiveRange.to };
  return { from: selected, to: selected };
}

function renderIncidenciasExecutiveReport() {
  const tbody = document.getElementById("incidencias-exec-tbody");
  if (!tbody) return;

  const window = getIncExecutiveDateWindow();
  const from = window.from || "";
  const to = window.to || "";

  let base = filteredDespachosData.slice();
  if (from && to) {
    base = base.filter(
      (d) => d.fechaProgramada >= from && d.fechaProgramada <= to,
    );
  } else if (from && !to) {
    base = base.filter((d) => d.fechaProgramada >= from);
  } else if (!from && to) {
    base = base.filter((d) => d.fechaProgramada <= to);
  }

  const rows = [];
  base.forEach((d) => {
    (d.incidencias || []).forEach((inc) => {
      const tipo = inc?.tipo || "";
      const sev = inc?.severidad
        ? String(inc.severidad).toLowerCase()
        : getIncidenciaSeveridad(tipo).toLowerCase();

      rows.push({
        unidad: d.unidad || "",
        tipo: tipo,
        fecha: inc?.fecha || "",
        direccion: inc?.direccion || "",
        sev: sev,
      });
    });
  });

  const sevRank = (s) =>
    s === "rojo" ? 0 : s === "naranja" ? 1 : s === "verde" ? 2 : 3;
  rows.sort(
    (a, b) =>
      sevRank(a.sev) - sevRank(b.sev) ||
      String(a.unidad).localeCompare(String(b.unidad)),
  );

  tbody.innerHTML = rows
    .map((r) => {
      const sevLower = String(r.sev).toLowerCase();
      const incidenciaClass =
        sevLower === "rojo"
          ? "incidencia-rojo"
          : sevLower === "naranja"
            ? "incidencia-naranja"
            : "incidencia-verde";

      const badgeText =
        sevLower === "rojo"
          ? "🔴 CRÍTICA"
          : sevLower === "naranja"
            ? "🟠 RELEVANTE"
            : "🟢 ORDINARIA";

      return `
                    <tr class="${incidenciaClass}">
                      <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                          <span class="text-xs font-bold">${badgeText}</span>
                          <span class="font-semibold">${escapeHtml(r.unidad)}</span>
                        </div>
                      </td>
                      <td class="px-4 py-3 font-medium">${escapeHtml(r.tipo)}</td>
                      <td class="px-4 py-3">${escapeHtml(r.fecha)}</td>
                      <td class="px-4 py-3">${escapeHtml(r.direccion)}</td>
                    </tr>
                  `;
    })
    .join("");
  if (rows.length === 0) {
    tbody.innerHTML = `<tr><td class="px-4 py-6 text-center text-gray-500" colspan="4">Sin incidencias para el filtro seleccionado.</td></tr>`;
  }
}

async function guardarInformeEnBD() {
  if (userRole !== "editor" && userRole !== "admin") {
    alert("No tienes permisos para guardar informes.");
    return;
  }
  if (!lastInformeData || lastInformeData.length === 0) {
    alert("No hay datos de informe para guardar.");
    return;
  }

  const total = lastInformeData.length;
  const realizados = lastInformeData.filter(
    (d) => d.estatus === "Despacho realizado",
  );
  const aTiempo = realizados.filter(
    (d) =>
      d.realDescarga &&
      checkTimeDeviation(d.citaDescarga, d.realDescarga) === "estatus-verde",
  ).length;
  const conRetraso = realizados.length - aTiempo;
  const enRuta = lastInformeData.filter((d) => d.estatus === "En ruta").length;
  const programados = lastInformeData.filter(
    (d) => d.estatus === "Programado",
  ).length;
  const totalIncidencias = lastInformeData.reduce(
    (acc, d) => acc + (d.incidencias ? d.incidencias.length : 0),
    0,
  );

  const operadorMonitoreo =
    lastInformeData[0]?.operadorMonitoreoId || "Desconocido";
  const fechaDespacho =
    document.getElementById("date-filter")?.value ||
    new Date().toISOString().split("T")[0];

  const titulo = prompt(
    "Ingrese un título para el informe:",
    `Informe de Despachos - ${fechaDespacho} - Operador: ${operadorMonitoreo} - Total: ${total}`,
  );

  if (!titulo) return;

  const btnGuardar = document.getElementById("guardar-informe-btn");
  const originalText = btnGuardar?.textContent;
  if (btnGuardar) {
    btnGuardar.textContent = "Guardando...";
    btnGuardar.disabled = true;
  }

  try {
    const payload = {
      titulo,
      fecha_despacho: fechaDespacho,
      total_despachos: total,
      a_tiempo: aTiempo,
      con_retraso: conRetraso,
      en_ruta: enRuta,
      programados,
      total_incidencias: totalIncidencias,
      operador_monitoreo: operadorMonitoreo,
      datos_informe: lastInformeData,
    };

    const res = await fetch("/bitacora_/src/informe/guardar_informe.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    const text = await res.text();

    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      throw new Error(
        `Respuesta inválida del servidor (no JSON). Inicio: ${text.substring(
          0,
          200,
        )}`,
      );
    }

    if (!res.ok || !data?.success) {
      throw new Error(data?.message || `Error HTTP ${res.status}`);
    }

    alert(`✅ Informe guardado correctamente (ID: ${data.id})`);
  } catch (error) {
    alert(`❌ Error al guardar en BD: ${error.message}`);
  } finally {
    if (btnGuardar) {
      btnGuardar.textContent = originalText || "Guardar Informe";
      btnGuardar.disabled = false;
    }
  }
}
let informesGuardadosCache = [];

function getNormalizedString(v) {
  return (v ?? "")
    .toString()
    .trim()
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "");
}

function parseInformeDate(value) {
  if (!value) return null;
  const s = value.toString().trim();
  if (!s) return null;
  const candidates = [
    dayjs(s, "YYYY-MM-DD", true),
    dayjs(s, "DD/MM/YYYY", true),
    dayjs(s, "YYYY-MM-DD HH:mm:ss", true),
    dayjs(s),
  ];
  return candidates.find((d) => d && d.isValid()) || null;
}

function setDbStatus(text) {
  const el = document.getElementById("db-status");
  if (el) el.textContent = text;
}

function setInformesFilterSummary(shown, total) {
  const el = document.getElementById("informes-filtro-resumen");
  if (!el) return;
  el.textContent = `Mostrando ${shown} de ${total}`;
}

function applyInformesFilters() {
  const container = document.getElementById("informes-lista-container");
  if (!container) return;

  const all = Array.isArray(informesGuardadosCache)
    ? informesGuardadosCache
    : [];

  const q = getNormalizedString(
    document.getElementById("informes-filtro-texto")?.value,
  );
  const unidadSel = (
    document.getElementById("informes-filtro-unidad")?.value ?? ""
  )
    .toString()
    .trim();
  const soloIncidencias = Boolean(
    document.getElementById("informes-filtro-solo-incidencias")?.checked,
  );
  const desde = parseInformeDate(
    document.getElementById("informes-filtro-fecha-desde")?.value,
  );
  const hasta = parseInformeDate(
    document.getElementById("informes-filtro-fecha-hasta")?.value,
  );

  const filtered = all.filter((inf) => {
    if (!inf) return false;

    if (q) {
      const haystack = [
        inf.id,
        inf.titulo,
        inf.operador_monitoreo,
        inf.fecha_despacho,
        ...(getUnidadesFromInforme(inf) || []),
      ]
        .map(getNormalizedString)
        .join(" ");
      if (!haystack.includes(q)) return false;
    }

    if (unidadSel) {
      const unidades = getUnidadesFromInforme(inf);
      if (!unidades.includes(unidadSel)) return false;
    }

    if (soloIncidencias) {
      const ti = parseInt(inf.total_incidencias, 10) || 0;
      if (ti <= 0) return false;
    }

    if (desde || hasta) {
      const d = parseInformeDate(inf.fecha_despacho);
      if (!d) return false;
      if (desde && d.isBefore(desde, "day")) return false;
      if (hasta && d.isAfter(hasta, "day")) return false;
    }

    return true;
  });

  setInformesFilterSummary(filtered.length, all.length);
  mostrarInformes(filtered, container);
}

function resetInformesFilters() {
  document.getElementById("informes-filtro-texto").value = "";
  document.getElementById("informes-filtro-fecha-desde").value = "";
  document.getElementById("informes-filtro-fecha-hasta").value = "";
  document.getElementById("informes-filtro-unidad").value = "";
  document.getElementById("informes-filtro-solo-incidencias").checked = false;
  applyInformesFilters();
}

function getUnidadesFromInforme(inf) {
  if (!inf) return [];
  let detalles = [];
  if (Array.isArray(inf.datos_informe_decoded)) {
    detalles = inf.datos_informe_decoded;
  } else if (inf.datos_informe) {
    try {
      const decoded = JSON.parse(inf.datos_informe);
      if (Array.isArray(decoded)) detalles = decoded;
    } catch (e) {
      console.warn("Error parsing datos_informe:", e);
    }
  }
  const unidades = detalles
    .map((d) => (d?.unidad ?? "").toString().trim())
    .filter(Boolean);
  return [...new Set(unidades)];
}

function populateUnidadInformeFilter(informes) {
  const sel = document.getElementById("informes-filtro-unidad");
  if (!sel) return;

  const current = (sel.value ?? "").toString();
  const allUnidades = new Set();

  (informes || []).forEach((inf) => {
    const unidades = getUnidadesFromInforme(inf);
    unidades.forEach((u) => allUnidades.add(u));
  });

  const unidadesArray = [...allUnidades].sort((a, b) => a.localeCompare(b));

  sel.innerHTML = '<option value="">Todas</option>';
  unidadesArray.forEach((unidad) => {
    const opt = document.createElement("option");
    opt.value = unidad;
    opt.textContent = unidad;
    sel.appendChild(opt);
  });

  sel.value = unidadesArray.includes(current) ? current : "";
}

async function cargarInformesGuardados() {
  const container = document.getElementById("informes-lista-container");
  if (!container) return;

  container.innerHTML = `
                <div class="text-center p-6">
                  <p class="text-gray-500">Cargando informes guardados...</p>
                  <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500 mt-2"></div>
                </div>
              `;

  try {
    const res = await fetch("/bitacora_/src/informe/obtener_informes.php", {
      cache: "no-store",
    });
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      throw new Error(
        `Respuesta inválida del servidor (no JSON). Inicio: ${text.substring(
          0,
          200,
        )}`,
      );
    }

    if (!res.ok) {
      throw new Error(data?.message || `Error HTTP ${res.status}`);
    }

    if (data && data.success && Array.isArray(data.data)) {
      informesGuardadosCache = data.data;
      populateUnidadInformeFilter(informesGuardadosCache);
      applyInformesFilters();
    } else if (Array.isArray(data)) {
      informesGuardadosCache = data;
      populateUnidadInformeFilter(informesGuardadosCache);
      applyInformesFilters();
    } else {
      container.innerHTML = `
                    <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg text-yellow-800">
                      ${data?.message || "No se pudo cargar la lista de informes."}
                    </div>
                  `;
      informesGuardadosCache = [];
      setInformesFilterSummary(0, 0);
    }

    const status = document.getElementById("db-status");
    if (status)
      status.textContent = "Estado de la Base de Datos: Conectado (MySQL)";
  } catch (error) {
    container.innerHTML = `
                  <div class="bg-red-50 border border-red-200 p-4 rounded-lg">
                    <p class="text-red-800 font-semibold">Error al cargar informes</p>
                    <p class="text-red-700 text-sm mt-1">${String(
                      error.message || error,
                    )}</p>
                    <div class="mt-3 flex gap-2">
                      <button onclick="cargarInformesGuardados()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Reintentar</button>
                    </div>
                  </div>
                `;
    const status = document.getElementById("db-status");
    if (status)
      status.textContent =
        "Estado de la Base de Datos: Error consultando MySQL";
  }
}

function mostrarInformes(informes, container) {
  if (!informes || informes.length === 0) {
    const total = Array.isArray(informesGuardadosCache)
      ? informesGuardadosCache.length
      : 0;
    setInformesFilterSummary(0, total);
    container.innerHTML = `
                  <div class="text-center p-10 bg-gray-50 rounded-lg">
                      <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                      </svg>
                      <h3 class="text-lg font-medium text-gray-700">No hay informes guardados</h3>
                      <p class="text-gray-500 mt-2">Los informes que guardes aparecerán aquí.</p>
                      <p class="text-gray-400 text-sm mt-1">Ve a la pestaña "06. Informe Ejecutivo" para crear uno.</p>
                  </div>
                `;
    return;
  }

  try {
    const total = Array.isArray(informesGuardadosCache)
      ? informesGuardadosCache.length
      : informes.length;
    setInformesFilterSummary(informes.length, total);
  } catch (e) {}

  informes.sort((a, b) => {
    const dateA = new Date(a.fecha_creacion || 0);
    const dateB = new Date(b.fecha_creacion || 0);
    return dateB - dateA;
  });

  let html = `<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">`;

  informes.forEach((informe) => {
    const id = parseInt(informe.id, 10) || 0;
    const titulo = informe.titulo?.toString() || "Sin título";
    const fechaDespacho =
      informe.fecha_despacho?.toString() || "No especificada";
    const operador =
      informe.operador_monitoreo?.toString() || "No especificado";

    let fechaFormateada = "Fecha no disponible";
    try {
      let fechaObj;
      if (informe.fecha_creacion && informe.fecha_creacion.toDate) {
        fechaObj = informe.fecha_creacion.toDate();
      } else if (informe.fecha_creacion) {
        fechaObj = new Date(informe.fecha_creacion);
      }
      if (fechaObj && !isNaN(fechaObj.getTime())) {
        fechaFormateada = fechaObj.toLocaleString("es-MX", {
          year: "numeric",
          month: "long",
          day: "numeric",
          hour: "2-digit",
          minute: "2-digit",
        });
      }
    } catch (e) {
      console.warn("Error formateando fecha:", e);
    }

    const totalDespachos = parseInt(informe.total_despachos) || 0;
    const aTiempo = parseInt(informe.a_tiempo) || 0;
    const conRetraso = parseInt(informe.con_retraso) || 0;
    const totalIncidencias = parseInt(informe.total_incidencias) || 0;

    html += `
                  <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow duration-300">
                      <div class="p-5">
                          <div class="flex justify-between items-start mb-3">
                              <h3 class="font-bold text-lg text-blue-800 truncate" title="${titulo}">
                                  ${titulo}
                              </h3>
                              <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded-full whitespace-nowrap">
                          ID: ${id || "N/A"}
                              </span>
                          </div>
                          <div class="flex items-center text-sm text-gray-500 mb-4">
                              <svg class="w-4 h-4 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                              </svg>
                              <span class="truncate">${fechaFormateada}</span>
                          </div>
                          <div class="grid grid-cols-2 gap-3 mb-4">
                              <div class="bg-gray-50 p-3 rounded-lg">
                                  <p class="text-xs text-gray-500">Total Despachos</p>
                                  <p class="text-lg font-bold">${totalDespachos}</p>
                              </div>
                              <div class="bg-green-50 p-3 rounded-lg">
                                  <p class="text-xs text-gray-500">A Tiempo</p>
                                  <p class="text-lg font-bold text-green-600">${aTiempo}</p>
                              </div>
                              <div class="bg-red-50 p-3 rounded-lg">
                                  <p class="text-xs text-gray-500">Con Retraso</p>
                                  <p class="text-lg font-bold text-red-600">${conRetraso}</p>
                              </div>
                              <div class="bg-yellow-50 p-3 rounded-lg">
                                  <p class="text-xs text-gray-500">Incidencias</p>
                                  <p class="text-lg font-bold text-yellow-600">${totalIncidencias}</p>
                              </div>
                          </div>
                          <div class="text-sm text-gray-600 mb-4 space-y-1">
                              <p class="truncate"><strong class="text-gray-700">Operador:</strong> ${operador}</p>
                              <p><strong class="text-gray-700">Fecha Despacho:</strong> ${fechaDespacho}</p>
                          </div>
                          <div class="flex space-x-2">
                            <button onclick="verInformeDetalle(${id})"
                                       class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors duration-200">
                                  Ver Detalle
                              </button>
                            <button onclick="eliminarInforme(${id})"
                                       class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm font-medium transition-colors duration-200"
                                      title="Eliminar informe">
                                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                  </svg>
                              </button>
                          </div>
                      </div>
                  </div>
                `;
  });

  html += `</div>`;
  container.innerHTML = html;
}

async function verInformeDetalle(id) {
  const numericId = parseInt(id, 10);
  if (!numericId || numericId <= 0) return alert("ID de informe inválido.");

  const body = document.getElementById("modal-informe-body");
  const title = document.getElementById("modal-informe-titulo");
  if (title) title.textContent = "Cargando...";
  if (body) {
    body.innerHTML = `
                  <div class="text-center p-6">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500"></div>
                    <p class="mt-2 text-gray-500">Cargando detalles...</p>
                  </div>
                `;
  }
  openModal("modal-informe-detalle");

  try {
    const res = await fetch(
      `informe/obtener_informe_detalle.php?id=${encodeURIComponent(numericId)}`,
      { cache: "no-store" },
    );
    const text = await res.text();
    let payload;
    try {
      payload = JSON.parse(text);
    } catch (e) {
      throw new Error(
        `Respuesta inválida del servidor (no JSON). Inicio: ${text.substring(
          0,
          200,
        )}`,
      );
    }
    if (!res.ok || !payload?.success)
      throw new Error(payload?.message || `Error HTTP ${res.status}`);

    const row = payload.data || {};
    if (title) title.textContent = `${row.titulo || "Informe"}`;

    let detalles = null;
    if (Array.isArray(row.datos_informe_decoded))
      detalles = row.datos_informe_decoded;
    else if (row.datos_informe) {
      try {
        const decoded = JSON.parse(row.datos_informe);
        if (Array.isArray(decoded)) detalles = decoded;
      } catch (e) {}
    }

    const resumenHtml = `
                <div class="space-y-2">
                  <div class="bg-blue-50 border border-blue-100 rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                      <p><span class="font-medium text-gray-700">Fecha de creación:</span> ${escapeHtml(
                        row.fecha_creacion || "",
                      )}</p>
                      <p><span class="font-medium text-gray-700">Fecha de despacho:</span> ${escapeHtml(
                        row.fecha_despacho || "",
                      )}</p>
                      <p><span class="font-medium text-gray-700">Operador:</span> ${escapeHtml(
                        row.operador_monitoreo || "",
                      )}</p>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-3">
                        <div class="bg-gray-100 p-4 rounded-lg text-center">
                          <p class="text-sm font-medium text-gray-600">Total Despachos</p>
                            <p class="text-2xl font-bold mt-1">${escapeHtml(
                              row.total_despachos || 0,
                            )}</p>
                        </div>
                        <div class="bg-green-100 p-4 rounded-lg text-center">
                          <p class="text-sm font-medium text-green-700">A Tiempo</p>
                          <p class="text-2xl font-bold text-green-800 mt-1">
                              ${escapeHtml(row.a_tiempo || 0)}
                          </p>
                        </div>
                        <div class="bg-red-100 p-4 rounded-lg text-center">
                          <p class="text-sm font-medium text-red-700">Con Retraso</p>
                          <p class="text-2xl font-bold text-red-800 mt-1">
                              ${escapeHtml(row.con_retraso || 0)}
                          </p>
                        </div>
                        <div class="bg-yellow-100 p-4 rounded-lg text-center">
                          <p class="text-sm font-medium text-yellow-700">Incidencias</p>
                          <p class="text-2xl font-bold text-yellow-800 mt-1">${escapeHtml(
                            row.total_incidencias || 0,
                          )}</p>
                        </div>
                    </div>
                  </div>
                </div>
                `;

    let detallesHtml = "";
    if (Array.isArray(detalles) && detalles.length) {
      const head = `
                    <div class="overflow-x-auto rounded-xl border border-gray-200 mt-4">
                      <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                          <tr>
                            <th class="text-left p-3">Folio</th>
                            <th class="text-left p-3">Unidad</th>
                            <th class="text-left p-3">Ruta</th>
                            <th class="text-left p-3">Estatus</th>
                            <th class="text-left p-3">Incidencias</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                  `;
      const rows = detalles
        .slice(0, 200)
        .map((d) => {
          const ruta = `${d.origen || ""} → ${d.destino || ""}`.trim();
          const inc = Array.isArray(d.incidencias) ? d.incidencias.length : 0;
          return `
                        <tr>
                          <td class="p-3 whitespace-nowrap">${escapeHtml(
                            d.folio || "",
                          )}</td>
                          <td class="p-3 whitespace-nowrap font-semibold">${escapeHtml(
                            d.unidad || "",
                          )}</td>
                          <td class="p-3">${escapeHtml(ruta)}</td>
                          <td class="p-3 whitespace-nowrap">${escapeHtml(
                            d.estatus || "",
                          )}</td>
                          <td class="p-3 whitespace-nowrap">${inc}</td>
                        </tr>
                      `;
        })
        .join("");
      const foot = `</tbody></table></div>`;
      detallesHtml = `
                    <h4 class="text-lg font-semibold text-gray-800 mt-6 mb-2">Detalle (primeros 200)</h4>
                    ${head}${rows}${foot}
                  `;
    }

    if (body) body.innerHTML = `${resumenHtml}${detallesHtml}`;
  } catch (err) {
    if (title) title.textContent = "Error";
    if (body)
      body.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-800">
                      <strong>No se pudo cargar el detalle:</strong> ${escapeHtml(
                        String(err?.message || err),
                      )}
                    </div>
                  `;
  }
}

async function eliminarInforme(id) {
  if (userRole !== "editor" && userRole !== "admin") {
    alert("No tienes permisos para eliminar informes.");
    return;
  }
  const numericId = parseInt(id, 10);
  if (!numericId || numericId <= 0) return alert("ID de informe inválido.");
  if (!confirm(`¿Eliminar el informe ID ${numericId}?`)) return;

  try {
    const res = await fetch("/bitacora_/src/informe/eliminar_informe.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: numericId }),
    });
    const text = await res.text();
    let payload;
    try {
      payload = JSON.parse(text);
    } catch (e) {
      throw new Error(
        `Respuesta inválida del servidor (no JSON). Inicio: ${text.substring(
          0,
          200,
        )}`,
      );
    }
    if (!res.ok || !payload?.success)
      throw new Error(payload?.message || `Error HTTP ${res.status}`);

    alert("Informe eliminado correctamente.");
    cargarInformesGuardados();
  } catch (err) {
    alert(`Error al eliminar: ${err?.message || err}`);
  }
}
function renderValidacionesGPS() {
  const tbody = document.getElementById("validaciones-table-body");
  if (!tbody) return;

  populateValidacionesFilters();

  renderValidacionesTable();
}

function populateValidacionesFilters() {
  const selectCliente = document.getElementById("validaciones-filtro-cliente");
  if (!selectCliente) return;

  const clientes = [
    ...new Set(filteredDespachosData.map((d) => d.cliente).filter(Boolean)),
  ].sort();

  const currentValue = selectCliente.value;
  selectCliente.innerHTML = '<option value="">Todos los clientes</option>';
  clientes.forEach((cliente) => {
    selectCliente.innerHTML += `<option value="${escapeHtml(cliente)}">${escapeHtml(cliente)}</option>`;
  });
  selectCliente.value = currentValue;
}

function applyValidacionesFilters() {
  renderValidacionesTable();
}

function renderValidacionesTable() {
  const tbody = document.getElementById("validaciones-table-body");
  if (!tbody) return;

  const filtroUnidad = (
    document.getElementById("validaciones-filtro-unidad")?.value || ""
  )
    .trim()
    .toLowerCase();
  const filtroCliente =
    document.getElementById("validaciones-filtro-cliente")?.value || "";
  const filtroEstado =
    document.getElementById("validaciones-filtro-estado")?.value || "";

  let datosFiltrados = filteredDespachosData.filter((d) => {
    if (
      filtroUnidad &&
      !String(d.unidad || "")
        .toLowerCase()
        .includes(filtroUnidad)
    ) {
      return false;
    }

    if (filtroCliente && d.cliente !== filtroCliente) {
      return false;
    }

    if (filtroEstado === "validado" && !isGpsOperativo(d.gpsValidacionEstado)) {
      return false;
    }
    if (
      filtroEstado === "sin_validar" &&
      isGpsOperativo(d.gpsValidacionEstado)
    ) {
      return false;
    }

    return true;
  });

  if (datosFiltrados.length === 0) {
    tbody.innerHTML = `
                  <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                      No hay registros que coincidan con los filtros aplicados.
                    </td>
                  </tr>
                `;
    return;
  }

  tbody.innerHTML = datosFiltrados
    .map((d, idx) => {
      const gpsOk = isGpsOperativo(d.gpsValidacionEstado);
      const statusClass = gpsOk
        ? "bg-green-100 text-green-800"
        : "bg-red-100 text-red-800";
      const statusText = gpsOk ? "Validado" : "Sin validación";
      const validacionesTexto = gpsOk
        ? formatGpsEstado(d.gpsValidacionEstado)
        : "-";

      return `
                  <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                      ${escapeHtml(d.unidad || "N/A")}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                      ${escapeHtml(d.cliente || "N/A")}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                      ${escapeHtml(d.operadorMonitoreoId || "No asignado")}
                    </td>
                    <td class="px-4 py-3 text-sm">
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                        ${statusText}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                      ${escapeHtml(validacionesTexto)}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                      ${d.gpsValidacionTimestamp ? formatDateTime(d.gpsValidacionTimestamp) : "Sin registro"}
                    </td>
                  </tr>
                `;
    })
    .join("");
}
(function init() {
  try {
    const savedRole = sessionStorage.getItem("bitacoraUserRole");
    const savedUnidades = sessionStorage.getItem("bitacoraUserUnidades");
    const savedTabs = sessionStorage.getItem("bitacoraUserTabs");
    const savedUsername = sessionStorage.getItem("bitacoraUsername");

    if (savedRole && savedUnidades && savedTabs && savedUsername) {
      username = savedUsername;
      userRole = savedRole;
      userUnidades = JSON.parse(savedUnidades);
      userTabs = JSON.parse(savedTabs);

      const userInfo = document.getElementById("user-info");
      const usernameDisplay = document.getElementById("username-display");
      if (userInfo && usernameDisplay) {
        usernameDisplay.textContent = username;
        userInfo.classList.remove("hidden");
      }

      updateTabVisibility();
    }
  } catch (e) {
    console.error("Error restoring session:", e);
  }

  if (!userRole)
    document.getElementById("login-modal").classList.remove("hidden");
  loadDataFromGoogleSheets(true);
})();
