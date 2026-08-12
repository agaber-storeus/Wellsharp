(function () {
  var assets = window.wellsharpPrototypeAssets || { logo: "/images/iadcLoginLgo.png" };
  var classModalData = window.wellsharpClassModalData || {};
  var temporaryProctorCode = null;
  var pendingExamButton = null;
  var pendingExamAction = "start";
  var pendingExamControl = null;
  var pendingProctorId = "";

  var prototypeClasses = {
    ended: {
      details: [
        ["Class ID:", "2183EA98"],
        ["Class Title or ID:", "Driller10"],
        ["Class Status:", '<span class="state-ended"></span>Test Ended'],
        ["Class Dates:", "July 13 - 16"],
        ["Exam Date/Time:", "07/17/2025 9:00 AM"],
        ["Started On:", "July 17, 2025 9:49 AM"],
        ["Ended On:", "September 16, 2025 11:26 AM"],
        ["Address:", "bro Training Camp<br>New Cairo, First Tagamoa villa 604<br>cairo"],
        ["Course Level:", "Drilling Operations Driller"],
        ["Stacks Offered:", "Surface Stack"],
        ["Supplement Offered:", "No Supplements Offered"],
        ["Instructor:", "Abraham Kotb"],
        ["Class Language:", "English"]
      ],
      showCodes: true
    },
    open: {
      details: [
        ["Class ID:", "03555D38"],
        ["Class Title or ID:", "IADC SUPER 11"],
        ["Class Status:", '<span class="state-green"></span>Test Open and Active <a class="tiny-red exam-state-btn" href="#">Stop Exam</a>'],
        ["Class Dates:", "August 25 - 29"],
        ["Exam Date/Time:", "08/29/2025 10:00 AM"],
        ["Started On:", "August 29, 2025 10:05 AM"],
        ["Ended On:", "n/a"],
        ["Address:", "Virtual<br>Virtual<br>Virtual"],
        ["Course Level:", "Drilling Operations Supervisor (Live)"],
        ["Stacks Offered:", "Surface Stack"],
        ["Supplement Offered:", "No Supplements Offered"],
        ["Instructor:", "Abraham Kotb"],
        ["Class Language:", "English"]
      ],
      showCodes: true
    },
    notstarted: {
      details: [
        ["Class ID:", "1C52A6B5"],
        ["Class Title or ID:", "supervisor 2506"],
        ["Class Status:", '<span class="state-blue"></span>Test Not Started <a class="tiny-blue exam-state-btn" href="#">Start Exam</a>'],
        ["Class Dates:", "June 20 - 24"],
        ["Exam Date/Time:", "06/24/2026 10:00 AM"],
        ["Started On:", "n/a"],
        ["Ended On:", "n/a"],
        ["Address:", "bro training camp<br>Bro training camp First tagamoa villa 604 Cairo<br>cairo"],
        ["Course Level:", "Drilling Operations Supervisor"],
        ["Stacks Offered:", "Surface Stack"],
        ["Supplement Offered:", "No Supplements Offered"],
        ["Instructor:", "Abraham Kotb"],
        ["Class Language:", "English"]
      ],
      showCodes: false
    }
  };

  var codeRows = [
    ["AL-GHAZI, HAMED RASHID KHALFAN", "halghazi", "zmx2c2", "XPO"],
    ["ALMANNAIE, RASHED KH R H", "ralmannaie", "az2xcm", "XPO"],
    ["ALMUQABQAB, SADIQ MOHAMMED", "salmuqabqab", "64mp3m", "XPO"],
    ["ALNAJEM, ABDULAZIZ A KH", "aalnajem", "2eze62", "XPO"],
    ["ALRUKAIBI, RASHID N R D", "ralrukaibi", "zngpar", "XPO"],
    ["CHENG, ZHU", "zcheng", "r7p3y2", "XPO"],
    ["FEI, LIU", "lfei", "fgx7ex", "XPO"],
    ["HARTONO, RUDI", "rhartono", "6j9cpz", "XPO"],
    ["Jabir, Alaa Majeed", "ajabir", "74cn7w", "XPO"],
    ["KOTHAPALLI, MURALI NAGA RAJU", "mkothapalli", "c4xpef", "XPO"],
    ["LIANG, CHANG", "cliang", "hzmzcx", "XPO"],
    ["MAHMOOD, ARSHAD", "amahmood", "6w4ckh", "XPO"],
    ["Michwit, Ahmed Zghaier", "amichwit", "ch46h2", "XPO"],
    ["PARAMASIVAM, MAGESH", "mparamasivam", "ejwjwx", "XPO"],
    ["SHAHHAT, AMR RAMADAN HUSSEIN", "ashahhat", "ncanxf", "XPO"],
    ["SIMATUPANG, HERMAN", "hsimatupang", "paftax", "XPO"],
    ["SOWILAM, AHMED SALAH MOHAMED", "asowilam", "2424jz", "XPO"],
    ["TABENA, JAWAD KADHIM", "jtabena", "7mcyc4", "XPO"]
  ];

  var scoreRows = [
    ["DJERDJOUR, MOHAMED", "77", "complete"],
    ["FAHAD, ALI HASAN", "80", "complete"],
    ["GHANIM, HAYDER RAHI", "81", "complete"],
    ["KAREEM, ZAINAB RAHEEM", "", "notstarted"],
    ["KHALAF, AHMED HAMAD", "88", "inprogress"],
    ["KHAMEES, KAREEM MOHAMMED", "79", "complete"],
    ["KOSHY, ROBIN", "80", "inprogress"],
    ["MOHAMMED, GHASSAN YASEEN", "92", "complete"],
    ["MOHAN, AKRAM ADIL", "80", "complete"],
    ["NAJM, MOHAMMED ADDULKAREEM", "75", "noshow"],
    ["NEAMAH, SADDAM KADHIM", "77", "complete"],
    ["SODE, ABDULRAZAQ ATIYAH", "90", "complete"]
  ];

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
      }[char];
    });
  }

  function ensureModal() {
    var modal = document.getElementById("sharedClassModal");
    if (modal) {
      return modal;
    }

    var overlay = document.createElement("div");
    overlay.id = "sharedClassOverlay";
    overlay.className = "overlay";

    modal = document.createElement("section");
    modal.id = "sharedClassModal";
    modal.className = "dashboard-modal shared-class-modal";
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");

    document.body.appendChild(overlay);
    document.body.appendChild(modal);

    overlay.addEventListener("click", closeModal);
    return modal;
  }

  function closeModal() {
    var overlay = document.getElementById("sharedClassOverlay");
    var modal = document.getElementById("sharedClassModal");
    closeProctorCheck();
    if (overlay) overlay.remove();
    if (modal) modal.remove();
  }

  function closeProctorCheck() {
    var overlay = document.getElementById("proctorCheckOverlay");
    var modal = document.getElementById("proctorCheckModal");
    if (overlay) overlay.remove();
    if (modal) modal.remove();
  }

  function proctorCheckMarkup(action, control) {
    var isEnding = action === "end" || action === "stop";
    var buttonText = isEnding ? "End Class" : "Start Class";
    var schedules = control && Array.isArray(control.scheduledFor) ? control.scheduledFor : [];
    var scheduledText = schedules.length
      ? schedules.map(function (schedule) {
        var range = [schedule.start, schedule.end].filter(Boolean).join(" to ");
        return escapeHtml(schedule.name + (range ? " (" + range + ")" : ""));
      }).join(", ")
      : "No linked Exam schedule";
    var controlNote = control
      ? '<p class="proctor-note"><strong>This controls the linked Class.</strong> The Admin Exam schedule dates do not block this action after an authorized Proctor ID is verified.</p>'
      : "";

    return [
      '<a class="modal-close" href="#" data-proctor-close>x</a>',
      '<h2>Enter Proctor\'s ID:</h2>',
      '<div class="proctor-code-row">',
      '<input id="proctorCodeInput" placeholder="Proctor ID" autocomplete="off" />',
      '<button class="tiny-green proctor-check-btn" type="button" data-proctor-check>Check</button>',
      '</div>',
      '<div id="proctorCheckMessage" class="message-bar is-hidden"></div>',
      '<button id="proctorLaunchButton" class="tiny-green launch-class disabled" type="button" data-proctor-launch disabled>' + buttonText + '</button>',
      '<h3>Linked Exam: ' + scheduledText + '</h3>',
      controlNote,
      '<p class="proctor-note">If a proctor does not show up by the time the assessment is scheduled to begin, call the <strong>emergency proctor</strong> numbers shown below. You will be given an emergency code for beginning the assessment.</p>',
      '<table class="emergency-table">',
      '<thead><tr><th>Name</th><th>Number(s)</th><th>Email</th><th>Locations Covered</th></tr></thead>',
      '<tbody>',
      '<tr><td>Michael Rogers</td><td>+1-713-377-6282</td><td><a href="#">michael.rogers@lr.org</a></td><td>North and South America</td></tr>',
      '<tr><td>Lucy Bhalla</td><td>+91-989-276-7809</td><td><a href="#">Lucy.Bhalla@lr.org</a></td><td>Europe, Middle East and India</td></tr>',
      '<tr><td>Hazlan Mohd Hatta</td><td>+603-9212-2327</td><td><a href="#">Hazlan.Mohdhatta@lr.org</a></td><td>Asia and Australia</td></tr>',
      '<tr><td>IADC</td><td>+1-713-292-1945</td><td><a href="#">proctor@iadc.org</a></td><td>Backup contact</td></tr>',
      '</tbody>',
      '</table>'
    ].join("");
  }

  function openProctorCheck(button, control) {
    pendingExamButton = button;
    pendingExamControl = control || null;
    pendingExamAction = pendingExamControl
      ? button.getAttribute("data-exam-action") || (pendingExamControl.status === "active" ? "end" : "start")
      : (button.textContent.indexOf("Stop") !== -1 ? "stop" : "start");
    pendingProctorId = "";

    closeProctorCheck();

    var overlay = document.createElement("div");
    overlay.id = "proctorCheckOverlay";
    overlay.className = "proctor-code-overlay";

    var modal = document.createElement("section");
    modal.id = "proctorCheckModal";
    modal.className = "proctor-check";
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    modal.innerHTML = proctorCheckMarkup(pendingExamAction, pendingExamControl);

    document.body.appendChild(overlay);
    document.body.appendChild(modal);

    var input = document.getElementById("proctorCodeInput");
    if (input) {
      input.focus();
    }
  }

  function setProctorMessage(type, text) {
    var message = document.getElementById("proctorCheckMessage");
    var launch = document.getElementById("proctorLaunchButton");

    if (!message || !launch) {
      return;
    }

    message.className = "message-bar " + type;
    message.textContent = text;

    if (type === "success") {
      launch.disabled = false;
      launch.classList.remove("disabled");
    } else {
      launch.disabled = true;
      launch.classList.add("disabled");
    }
  }

  function checkProctorCode() {
    var input = document.getElementById("proctorCodeInput");
    var value = input ? input.value.trim() : "";

    if (pendingExamControl) {
      if (!value) {
        setProctorMessage("error", "Enter the Proctor ID first.");
        return;
      }

      var verifyButton = document.querySelector("[data-proctor-check]");
      if (verifyButton) verifyButton.disabled = true;
      setProctorMessage("error", "Checking Proctor ID...");

      fetch(pendingExamControl.verifyUrl, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
        },
        body: JSON.stringify({ proctor_id: value })
      }).then(function (response) {
        return response.json().then(function (data) {
          return { ok: response.ok, data: data };
        });
      }).then(function (result) {
        if (!result.ok) {
          var errors = result.data.errors || {};
          var message = errors.proctor_id ? errors.proctor_id[0] : (result.data.message || "No proctor found with this ID");
          pendingProctorId = "";
          setProctorMessage("error", message);
          return;
        }

        pendingProctorId = value;
        setProctorMessage("success", "Proctor found: " + result.data.proctor_name);
      }).catch(function () {
        pendingProctorId = "";
        setProctorMessage("error", "The Proctor ID could not be verified. Try again.");
      }).finally(function () {
        if (verifyButton) verifyButton.disabled = false;
      });
      return;
    }

    setProctorMessage("error", "This Class has no live Proctor verification endpoint.");
  }

  function finishExamStateChange(responseData) {
    if (pendingExamButton) {
      pendingExamButton.textContent = pendingExamAction === "start" ? "Class Started" : "Class Ended";
      pendingExamButton.classList.add("is-released");
    }

    if (pendingExamControl && currentClassId && classModalData[currentClassId]) {
      var updatedStatus = responseData?.class?.status || (pendingExamAction === "start" ? "active" : "completed");
      var statusText = updatedStatus === "active" ? "Active" : (updatedStatus === "completed" ? "Completed" : updatedStatus);
      var statusClass = updatedStatus === "active" ? "state-green" : "state-ended";
      classModalData[currentClassId].examControl.status = updatedStatus;
      classModalData[currentClassId].details = classModalData[currentClassId].details.map(function (item) {
        return item[0] === "Class Status:" ? [item[0], statusText, statusClass] : item;
      });
    }

    closeProctorCheck();
  }

  function controlClass() {
    var launch = document.getElementById("proctorLaunchButton");
    if (!pendingExamControl || !pendingProctorId || !launch) return;

    launch.disabled = true;
    launch.classList.add("disabled");
    setProctorMessage("error", pendingExamAction === "start" ? "Starting Class..." : "Ending Class...");

    fetch(pendingExamControl.controlUrl, {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
      },
      body: JSON.stringify({ action: pendingExamAction, proctor_id: pendingProctorId })
    }).then(function (response) {
      return response.json().then(function (data) {
        return { ok: response.ok, data: data };
      });
    }).then(function (result) {
      if (!result.ok) {
        var errors = result.data.errors || {};
        var message = errors.action ? errors.action[0] : (errors.proctor_id ? errors.proctor_id[0] : (result.data.message || "The Class could not be updated."));
        setProctorMessage("error", message);
        return;
      }

      finishExamStateChange(result.data);
    }).catch(function () {
      setProctorMessage("error", "The Class could not be updated. Try again.");
    });
  }

  function modalShell(activeTab, body, extraClass) {
    var classContext = ' data-class-state="' + escapeHtml(currentClass) + '" data-class-id="' + escapeHtml(currentClassId || '') + '"';

    return [
      '<a class="modal-close" href="#" data-class-close>x</a>',
      '<div class="dash-head">',
      "<h1>Class Dashboard</h1>",
      '<img src="' + assets.logo + '" alt="IADC WellSharp" />',
      "</div>",
      '<nav class="tabs">',
      '<a class="tab ' + (activeTab === "details" ? "active" : "") + '" href="#" data-class-modal="details"' + classContext + '>Class Details</a>',
      '<a class="tab ' + (activeTab === "scores" ? "active" : "") + '" href="#" data-class-modal="scores"' + classContext + '>Scores &amp; Reports</a>',
      "</nav>",
      '<div class="' + extraClass + '">',
      body,
      "</div>"
    ].join("");
  }

  var currentClass = "ended";
  var currentClassId = null;

  function getClassConfig() {
    return classModalData[currentClassId] || { details: [["Class Status:", "Live Class data unavailable"]], codeRows: [], scoreRows: [], examControl: null };
  }

  function renderDetails() {
    var config = getClassConfig();
    var details = config.details.map(function (item) {
      var value = item[2] ? '<span class="' + item[2] + '"></span>' + escapeHtml(item[1]) : escapeHtml(item[1]);
      return "<dt>" + escapeHtml(item[0]) + "</dt><dd>" + value + "</dd>";
    }).join("");

    var codes = (config.codeRows || []).length
      ? config.codeRows.map(function (row) {
        return "<tr><td>" + escapeHtml(row[0]) + "</td><td>" + escapeHtml(row[1]) + "</td><td>" + escapeHtml(row[2]) + "</td><td>" + escapeHtml(row[3]) + "</td></tr>";
      }).join("")
      : '<tr class="empty-code-row"><td colspan="4"></td></tr>';

    var examControl = config.examControl;
    var controlMarkup = "";
    if (examControl) {
      var controlAction = examControl.status === "active" ? "end" : "start";
      var canControl = examControl.status === "planned" || examControl.status === "active";
      var controlLabel = examControl.status === "active" ? "End Class" : "Start Class";
      var controlSchedule = Array.isArray(examControl.scheduledFor) && examControl.scheduledFor.length
        ? examControl.scheduledFor.map(function (schedule) {
          var range = [schedule.start, schedule.end].filter(Boolean).join(" to ");
          return escapeHtml(schedule.name + (range ? " (" + range + ")" : ""));
        }).join(", ")
        : "No linked Exam schedule";
      controlMarkup = '<section class="exam-control-panel"><h3>Class Control</h3><p>Linked Admin Exam: ' + controlSchedule + '</p>' + (canControl
        ? '<a href="#" class="' + (controlAction === "end" ? "tiny-red" : "tiny-blue") + ' exam-control-btn" data-exam-control data-exam-action="' + controlAction + '">' + controlLabel + '</a>'
        : '<p class="proctor-note">This Class is closed and cannot be controlled.</p>') + '</section>';
    }

    return modalShell("details", [
      '<div class="class-detail-column"><dl class="class-details">',
      details,
      "</dl>",
      controlMarkup,
      "</div>",
      "<div>",
      '<table class="codes-table"><thead><tr><th>Name</th><th>WellSharp ID</th><th>Enrollment</th><th>Company</th></tr></thead><tbody>',
      codes,
      "</tbody></table>",
      '<button class="print-btn" type="button" data-print-modal data-print-type="codes">Print/ Save Codes</button>',
      "</div>"
    ].join(""), "dash-content");
  }

  function scoreActions(row) {
    var state = row.state;

    if (state === "notstarted") {
      return ["<td>Not Started</td>", "<td>-</td>"];
    }

    if (state === "noshow") {
      return ["<td>No Show</td>", "<td>-</td>"];
    }

    var release = row.releasedAt
      ? '<span class="release-btn is-released">Released</span>'
      : '<a class="release-btn" href="#" data-release-url="' + escapeHtml(row.releaseUrl || "") + '">Release</a>';
    var report = row.reportUrl ? '<a class="score-btn" href="' + escapeHtml(row.reportUrl) + '">Score Report</a>' : "Score Report";
    var certificate = row.certificateUrl ? '<a class="release-btn certificate-download" href="' + escapeHtml(row.certificateUrl) + '">Lookup</a>' : "-";

    return [
      '<td>' + report + ' ' + release + '</td>',
      '<td>' + certificate + '</td>'
    ];
  }

  function printDetails(config) {
    return (config.details || []).map(function (item) {
      return '<div class="print-detail"><dt>' + escapeHtml(item[0]) + '</dt><dd>' + escapeHtml(item[1]) + '</dd></div>';
    }).join("");
  }

  function printStateLabel(state) {
    return {
      complete: "Complete",
      inprogress: "In progress",
      noshow: "No show",
      notstarted: "Not started"
    }[state] || "Pending";
  }

  function printStateClass(state) {
    return state === "complete" ? "success" : (state === "noshow" ? "danger" : "neutral");
  }

  function printRows(type, config) {
    if (type === "codes") {
      return (config.codeRows || []).map(function (row) {
        return '<tr><td class="person-cell">' + escapeHtml(row[0]) + '</td><td>' + escapeHtml(row[1]) + '</td><td><span class="status-chip success">' + escapeHtml(row[2]) + '</span></td><td>' + escapeHtml(row[3]) + '</td></tr>';
      }).join("") || '<tr><td colspan="4" class="empty-print">No enrolled trainees are stored for this Class.</td></tr>';
    }

    return (config.scoreRows || []).map(function (row) {
      var score = row.score ? escapeHtml(row.score) + "%" : "—";
      var state = printStateLabel(row.state);
      var certificate = row.certificateNumber ? "Issued · " + row.certificateNumber : "Not issued";
      var release = row.releasedAt ? "Released" : "Pending release";

      return '<tr><td class="person-cell">' + escapeHtml(row.name) + '</td><td class="score-cell">' + score + '</td><td><span class="status-chip ' + printStateClass(row.state) + '">' + state + '</span><small>Attempt ' + escapeHtml(row.attemptNumber || 1) + ' · ' + release + '</small></td><td>' + escapeHtml(certificate) + '</td></tr>';
    }).join("") || '<tr><td colspan="4" class="empty-print">No trainee attempts are stored for this Class.</td></tr>';
  }

  function showPrintPreview(documentMarkup) {
    var overlay = document.createElement("div");
    overlay.className = "class-print-preview-overlay";

    var modal = document.createElement("section");
    modal.className = "class-print-preview-modal";
    modal.innerHTML = '<div class="class-print-preview-toolbar"><strong>Document preview</strong><span><button type="button" data-print-preview-print>Print / Save PDF</button><button type="button" data-print-preview-download>Download HTML</button><button type="button" data-print-preview-close>Close</button></span></div><iframe title="Class document preview"></iframe>';

    var frame = modal.querySelector("iframe");
    var printButton = modal.querySelector("[data-print-preview-print]");
    var downloadButton = modal.querySelector("[data-print-preview-download]");
    var close = function () { overlay.remove(); modal.remove(); };

    modal.querySelector("[data-print-preview-close]").addEventListener("click", close);
    overlay.addEventListener("click", close);
    printButton.addEventListener("click", function () { frame.contentWindow?.focus(); frame.contentWindow?.print(); });
    downloadButton.addEventListener("click", function () {
      var link = document.createElement("a");
      link.href = URL.createObjectURL(new Blob([documentMarkup], { type: "text/html;charset=utf-8" }));
      link.download = "wellsharp-class-document.html";
      link.click();
      URL.revokeObjectURL(link.href);
    });

    document.body.append(overlay, modal);
    frame.srcdoc = documentMarkup;
  }

  function printClassDocument(type) {
    var config = getClassConfig();
    var isCodes = type === "codes";
    var title = isCodes ? "Class Roster &amp; Trainee Codes" : "Class Results Report";
    var subtitle = isCodes ? "Enrollment and WellSharp identification details" : "Assessment outcomes and certificate status";
    var headers = isCodes ? ["Name", "WellSharp ID", "Enrollment", "Company"] : ["Name", "Skills Score", "Knowledge Exam", "Certificate"];
    var generatedAt = new Date().toLocaleString();
    var tableClass = isCodes ? "codes-print-table" : "results-print-table";
    var documentMarkup = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' + title.replace(/&amp;/g, "&") + '</title><style>' +
      '@page{size:A4 landscape;margin:14mm}*{box-sizing:border-box}body{margin:0;background:#fff;color:#17364f;font:13px Arial,Helvetica,sans-serif}.print-sheet{max-width:1120px;margin:0 auto}.print-header{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;padding-bottom:18px;border-bottom:3px solid #e97825}.brand-lockup{display:flex;align-items:center;gap:14px}.brand-mark{width:58px;height:58px;object-fit:contain}.brand-name{color:#0e3554;font-size:19px;font-weight:700;letter-spacing:.04em}.brand-subtitle{margin-top:4px;color:#71808d;font-size:11px}.print-meta{text-align:right;color:#71808d;font-size:11px;line-height:1.5}.print-title{margin:24px 0 5px;color:#0e3554;font-size:25px}.print-subtitle{margin:0 0 18px;color:#71808d;font-size:13px}.print-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0 24px;margin-bottom:22px;padding:14px 16px;border:1px solid #d8e1e7;border-radius:8px;background:#f7fafb}.print-detail{display:grid;grid-template-columns:minmax(75px,auto) 1fr;gap:7px;padding:6px 0;border-bottom:1px solid #e5edf1}.print-detail dt{color:#71808d;font-weight:700}.print-detail dd{margin:0;color:#213547;overflow-wrap:anywhere}.print-table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border:1px solid #b9c7d2;border-radius:8px}.print-table th{padding:11px 12px;background:#0e3554;color:#fff;text-align:left;font-size:12px;letter-spacing:.04em}.print-table td{padding:11px 12px;border-top:1px solid #d8e1e7;vertical-align:top;line-height:1.3}.print-table tr:nth-child(even) td{background:#f2f6f8}.print-table tr:nth-child(odd) td{background:#fff}.print-table th:not(:last-child),.print-table td:not(:last-child){border-right:1px solid #d8e1e7}.person-cell{font-weight:700}.score-cell{font-size:16px;font-weight:700;color:#0e3554}.status-chip{display:inline-block;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap}.status-chip.success{background:#e7f7ee;color:#067647}.status-chip.danger{background:#fef0ee;color:#b42318}.status-chip.neutral{background:#edf2f7;color:#46566a}.print-table small{display:block;margin-top:5px;color:#71808d;font-size:10px}.empty-print{padding:28px!important;text-align:center;color:#71808d!important}.print-footer{display:flex;justify-content:space-between;gap:20px;margin-top:18px;padding-top:10px;border-top:1px solid #d8e1e7;color:#71808d;font-size:10px}.print-note{margin-top:13px;color:#71808d;font-size:10px}@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}.print-sheet{max-width:none}.no-print{display:none}}@media(max-width:800px){.print-detail-grid{grid-template-columns:1fr}.print-header{flex-direction:column}.print-meta{text-align:left}}' +
      '</style></head><body><main class="print-sheet"><header class="print-header"><div class="brand-lockup"><img class="brand-mark" src="' + escapeHtml(assets.logo) + '" alt="IADC WellSharp"><div><div class="brand-name">IADC WELLSHARP</div><div class="brand-subtitle">' + subtitle + '</div></div></div><div class="print-meta">Generated ' + escapeHtml(generatedAt) + '<br>WellSharp operational workspace</div></header><h1 class="print-title">' + title + '</h1><p class="print-subtitle">Class dashboard export</p><dl class="print-detail-grid">' + printDetails(config) + '</dl><table class="print-table ' + tableClass + '"><thead><tr>' + headers.map(function (header) { return '<th>' + header + '</th>'; }).join("") + '</tr></thead><tbody>' + printRows(type, config) + '</tbody></table><p class="print-note">This document was generated from the current Class dashboard data. Use the browser print dialog and choose “Save as PDF” to save a PDF copy.</p><footer class="print-footer"><span>WellSharp Class Dashboard</span><span>Confidential operational record</span></footer></main></body></html>';

    // Keep the popup reference. Chromium may return null when a popup is blocked.
    var printWindow = window.open("", "_blank", "width=1100,height=800");

    if (!printWindow) {
      showPrintPreview(documentMarkup);
      return;
    }

    try {
      printWindow.opener = null;
    } catch (error) {
      // The generated document is still safe to print if opener isolation is unavailable.
    }

    printWindow.document.open();
    printWindow.document.write(documentMarkup);
    printWindow.document.close();
    printWindow.focus();
    printWindow.addEventListener("afterprint", function () { printWindow.close(); });
    window.setTimeout(function () { printWindow.print(); }, 350);
  }

  function renderScores() {
    var config = getClassConfig();
    var scoreRows = config.scoreRows || [];
    var rows = scoreRows.map(function (row) {
      var actions = scoreActions(row);
      var score = row.score ? escapeHtml(row.score) + "%" : "-";
      return [
        "<tr>",
        "<td>" + escapeHtml(row.name) + "</td>",
        "<td>" + score + "</td>",
        actions[0],
        actions[1],
        "</tr>"
      ].join("");
    }).join("");

    return modalShell("scores", [
      '<table class="scores-table class-scores-table"><thead><tr><th>Name</th><th>Skills Score</th><th>Knowledge Exam</th><th>Certificate</th></tr></thead><tbody>',
      rows || '<tr><td colspan="4" class="scores-empty-cell">No trainee attempts are stored for this Class.</td></tr>',
      "</tbody></table>",
      '<button class="print-btn" type="button" data-print-modal data-print-type="results">Export Class Results</button>'
    ].join(""), "dash-content scores-mode");
  }

  function renderReport() {
    return [
      '<a class="modal-close" href="#" data-class-close>x</a>',
      '<div class="score-report-modal">',
      '<div class="report-top"><img src="' + assets.logo + '" alt="IADC WellSharp" /><a class="print-btn" href="#">Print/ Save</a></div>',
      "<h2>Knowledge Assessment Report</h2>",
      '<div class="score-report-scroll">',
      '<dl class="report-fields">',
      "<dt>Name:</dt><dd>AQIB SHAHZAD</dd>",
      "<dt>Assessment:</dt><dd>Drilling Operations, Supervisor, Surface</dd>",
      "<dt>Stack:</dt><dd>Surface Stack</dd>",
      "<dt>Assessment Date:</dt><dd>August 21, 2025 11:48 AM</dd>",
      "<dt>Score:</dt><dd>90%</dd>",
      "</dl>",
      "<h3>Instructions</h3>",
      "<p>Thank you for completing the IADC Well Control Knowledge Assessment for the course. You scored 90 percent on this knowledge assessment and, therefore, passed the course. If you passed your skills assessment you will be given your Certificate of Completion by your instructor, who will also review your missed questions with you.</p>",
      "<p>After your instructor reviews your exam results with you, you may choose to return to your computer to review each test question you missed on today's exam. To launch the review feature, log in using the same code you used at the beginning of the exam.</p>",
      "<p>Once you complete your review and you have received your Certificate, you are to log out of the testing system and may leave the testing center.</p>",
      "<h3>Topics for Review</h3>",
      "<p><strong>Barriers:</strong></p><ul><li><u>Testing Barriers</u><br><em>Explain positive pressure and negative pressure barrier tests.</em></li></ul>",
      "<p><strong>Equipment:</strong></p><ul><li><u>BOP Stack, Stack Valves, and Wellhead Components</u><br><em>Explain the purpose of the key components of equipment on the BOP Stack.</em></li><li><u>Mud-Gas Separator</u><br><em>Explain the purpose and location of the mud-gas separator in the circulating system.</em></li></ul>",
      "<p><strong>Managed Pressure Drilling (MPD):</strong></p><ul><li><u>MPD</u><br><em>Explain the value of using MPD techniques.</em></li></ul>",
      "</div>",
      "</div>"
    ].join("");
  }

  function renderFront() {
    return [
      '<a class="modal-close" href="#" data-class-close>x</a>',
      '<div class="certificate-card-modal">',
      '<div class="completion-card">',
      "<h2>IADC WellSharp Course Completion Card</h2>",
      '<p><strong>Trainee Name</strong><span>AHMED MOHAMED ELMASRY ABDALLA</span></p>',
      '<p><strong>Course Name</strong><span>Drilling Operations, Supervisor, Surface</span></p>',
      '<p><strong>Supplement Name</strong><span></span></p>',
      '<p><strong>Completion Date</strong><span>21 August 2025</span><strong>Expiration Date</strong><span>21 August 2027</span></p>',
      '<p><strong>Provider</strong><span>Bro Well Control School</span></p>',
      '<p><strong>Provider #</strong><span>00001179</span><strong>Phone #</strong><span>201012453893</span></p>',
      '<p><strong>Instructor Name</strong><span>Abraham Kotb</span></p>',
      '<footer>Certificate Number: 8C6D0EEE-F87B7A</footer>',
      "</div>",
      "</div>"
    ].join("");
  }

  function renderBack() {
    return [
      '<a class="modal-close" href="#" data-class-close>x</a>',
      '<div class="certificate-card-modal">',
      '<div class="qr-card">',
      '<img src="' + assets.logo + '" alt="IADC WellSharp" />',
      "<div>",
      "<p>This individual has successfully completed a well control course at an institution accredited by the International Association of Drilling Contractors.</p>",
      "<p>For scheduling training or replacement of lost card, please call the training provider with information provided on this completion card.</p>",
      "<p>To verify validity, please visit the IADC website:</p>",
      "<strong>www.iadc.org/wellsharp</strong>",
      "</div>",
      '<div class="fake-qr" aria-label="QR code"></div>',
      "</div>",
      "</div>"
    ].join("");
  }

  function openModal(type, classState, classId) {
    if (classState) {
      currentClass = classState;
    }
    currentClassId = classId || null;

    var modal = ensureModal();
    modal.className = "dashboard-modal shared-class-modal";

    if (type === "scores") {
      modal.innerHTML = renderScores();
    } else if (type === "report") {
      modal.classList.add("report-wrapper");
      modal.innerHTML = renderReport();
    } else if (type === "front") {
      modal.classList.add("certificate-wrapper");
      modal.innerHTML = renderFront();
    } else if (type === "back") {
      modal.classList.add("certificate-wrapper");
      modal.innerHTML = renderBack();
    } else {
      modal.innerHTML = renderDetails();
    }
  }

  document.addEventListener("click", function (event) {
    var release = event.target.closest("a.release-btn:not(.certificate-download)");
    if (release) {
      event.preventDefault();
      var releaseUrl = release.getAttribute("data-release-url");
      if (!releaseUrl) {
        release.textContent = "Unavailable";
        return;
      }
      release.textContent = "Releasing...";
      release.classList.add("is-released");
      fetch(releaseUrl, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
        }
      }).then(function (response) {
        if (!response.ok) throw new Error("Release failed");
        release.textContent = "Released";
      }).catch(function () {
        release.textContent = "Release failed";
        release.classList.remove("is-released");
      });
      return;
    }

    var print = event.target.closest("[data-print-modal]");
    if (print) {
      event.preventDefault();
      printClassDocument(print.getAttribute("data-print-type") || "codes");
      return;
    }

    var saveScore = event.target.closest(".save-score");
    if (saveScore) {
      event.preventDefault();
      var cell = saveScore.closest("td");
      var input = cell ? cell.querySelector(".score-input") : null;
      var value = input && input.value.trim() ? input.value.trim() : "81";

      if (cell) {
        cell.innerHTML = escapeHtml(value) + ' <a class="edit-score" href="#"><span class="edit-score-icon" aria-hidden="true"></span></a>';
      }
      return;
    }

    var examButton = event.target.closest(".exam-state-btn");
    if (examButton) {
      event.preventDefault();
      if (examButton.textContent.indexOf("Stop") !== -1) {
        var shouldStop = window.confirm("Are you sure that you would like to close this exam?  Do not stop class if all trainees have not completed their exams");
        if (shouldStop) {
          examButton.textContent = "Exam Stopped";
          examButton.classList.add("is-released");
        }
      } else {
        openProctorCheck(examButton);
      }
      return;
    }

    var examControlButton = event.target.closest("[data-exam-control]");
    if (examControlButton) {
      event.preventDefault();
      var action = examControlButton.getAttribute("data-exam-action");
      if (action === "end" && !window.confirm("Are you sure that you would like to close this Class? Do not stop it if all trainees have not completed their exams.")) {
        return;
      }
      openProctorCheck(examControlButton, getClassConfig().examControl);
      return;
    }

    var proctorClose = event.target.closest("[data-proctor-close]");
    if (proctorClose) {
      event.preventDefault();
      closeProctorCheck();
      return;
    }

    var proctorCheck = event.target.closest("[data-proctor-check]");
    if (proctorCheck) {
      event.preventDefault();
      checkProctorCode();
      return;
    }

    var proctorLaunch = event.target.closest("[data-proctor-launch]");
    if (proctorLaunch) {
      event.preventDefault();
      if (!proctorLaunch.disabled) {
        if (pendingExamControl) {
          controlClass();
        } else {
          finishExamStateChange();
        }
      }
      return;
    }

    var close = event.target.closest("[data-class-close]");
    if (close) {
      event.preventDefault();
      closeModal();
      return;
    }

    var trigger = event.target.closest("[data-class-modal]");
    if (!trigger) {
      return;
    }

    event.preventDefault();
    openModal(trigger.getAttribute("data-class-modal"), trigger.getAttribute("data-class-state"), trigger.getAttribute("data-class-id"));
  });
})();
