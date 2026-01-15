function downloadInformeTabAsHTML() {
  const tab = document.getElementById("tab-5");

  if (!tab)
    return alert("No se encontró el contenedor #tab-5 (Informe Ejecutivo).");

  const clone = tab.cloneNode(true);

  const btnInClone = clone.querySelector(
    'button[onclick="downloadInformeTabAsHTML()"]'
  );

  if (btnInClone) btnInClone.remove();

  const originalCanvases = tab.querySelectorAll("canvas");

  const cloneCanvases = clone.querySelectorAll("canvas");

  for (
    let i = 0;
    i < Math.min(originalCanvases.length, cloneCanvases.length);
    i++
  ) {
    const c = originalCanvases[i];

    try {
      const dataUrl = c.toDataURL("image/png");

      const img = document.createElement("img");

      img.src = dataUrl;

      img.alt = c.id || `grafico_${i + 1}`;

      img.style.maxWidth = "100%";

      img.style.height = "auto";

      cloneCanvases[i].replaceWith(img);
    } catch (e) {
      console.warn("No se pudo convertir un canvas a imagen (tab-5).", e);

      cloneCanvases[i].remove();
    }
  }

  const inlineStyles = Array.from(document.querySelectorAll("style"))

    .map((s) => s.innerHTML)

    .join("\n\n");

  const tailwindCdn = `<script src="https://cdn.tailwindcss.com"></script>`;

  const fontLink = `<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">`;

  const selectedDate = document.getElementById("date-filter")?.value || "";

  const safeDate = selectedDate || dayjs().format("YYYY-MM-DD");

  const filename = `Informe_Ejecutivo_${safeDate}.html`;

  const html = `<!doctype html>

<html lang="es">

<head>

  <meta charset="utf-8">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Informe Ejecutivo - ${escapeHtml(safeDate)}</title>

  ${fontLink}

  ${tailwindCdn}

  <style>

  ${inlineStyles}

  </style>

</head>

<body class="bg-gray-100">

  <div class="container mx-auto p-4 md:p-8">

    <div class="bg-white p-6 rounded-xl shadow-md">

      <div class="flex items-start justify-between gap-4 flex-wrap mb-4">

        <div>

          <h1 class="text-2xl font-extrabold text-gray-900">Informe Ejecutivo</h1>

          <p class="text-sm text-gray-500">Fecha: ${escapeHtml(safeDate)}</p>

        </div>

      </div>

 

      ${clone.innerHTML}

    </div>

  </div>

</body>

</html>`;

  const blob = new Blob([html], { type: "text/html;charset=utf-8" });

  const url = URL.createObjectURL(blob);

  const a = document.createElement("a");

  a.href = url;

  a.download = filename;

  document.body.appendChild(a);

  a.click();

  a.remove();

  URL.revokeObjectURL(url);
}
