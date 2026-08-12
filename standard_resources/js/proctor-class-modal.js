(function () {
  var certificatePdf = "assets/certificates/3518DC02-394D85.pdf";
  var temporaryProctorCode = "112233";
  var pendingExamButton = null;
  var pendingExamAction = "start";

  var classes = {
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

  function proctorCheckMarkup(action) {
    var buttonText = action === "stop" ? "Stop Exam" : "Launch Class";

    return [
      '<a class="modal-close" href="#" data-proctor-close>x</a>',
      '<h2>Enter Proctor\'s ID:</h2>',
      '<div class="proctor-code-row">',
      '<input id="proctorCodeInput" placeholder="Proctor ID" autocomplete="off" />',
      '<button class="tiny-green proctor-check-btn" type="button" data-proctor-check>Check</button>',
      '</div>',
      '<div id="proctorCheckMessage" class="message-bar is-hidden"></div>',
      '<button id="proctorLaunchButton" class="tiny-green launch-class disabled" type="button" data-proctor-launch disabled>' + buttonText + '</button>',
      '<h3>Exam scheduled for: 06/24/2026 10:00 AM</h3>',
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

  function openProctorCheck(button) {
    pendingExamButton = button;
    pendingExamAction = button.textContent.indexOf("Stop") !== -1 ? "stop" : "start";

    closeProctorCheck();

    var overlay = document.createElement("div");
    overlay.id = "proctorCheckOverlay";
    overlay.className = "proctor-code-overlay";

    var modal = document.createElement("section");
    modal.id = "proctorCheckModal";
    modal.className = "proctor-check";
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    modal.innerHTML = proctorCheckMarkup(pendingExamAction);

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

    if (value === temporaryProctorCode) {
      setProctorMessage("success", "Proctor found: Ashraf Amin Abd El- Rahman Aly");
    } else {
      setProctorMessage("error", "No proctor found with this ID");
    }
  }

  function finishExamStateChange() {
    if (pendingExamButton) {
      pendingExamButton.textContent = pendingExamAction === "start" ? "Exam Started" : "Exam Stopped";
      pendingExamButton.classList.add("is-released");
    }

    closeProctorCheck();
  }

  function modalShell(activeTab, body, extraClass) {
    return [
      '<a class="modal-close" href="#" data-class-close>x</a>',
      '<div class="dash-head">',
      "<h1>Class Dashboard</h1>",
      '<img src="assets/iadcLoginLgo.png" alt="IADC WellSharp" />',
      "</div>",
      '<nav class="tabs">',
      '<a class="tab ' + (activeTab === "details" ? "active" : "") + '" href="#" data-class-modal="details">Class Details</a>',
      '<a class="tab ' + (activeTab === "scores" ? "active" : "") + '" href="#" data-class-modal="scores">Scores &amp; Reports</a>',
      "</nav>",
      '<div class="' + extraClass + '">',
      body,
      "</div>"
    ].join("");
  }

  var currentClass = "ended";

  function getClassConfig() {
    return classes[currentClass] || classes.ended;
  }

  function renderDetails() {
    var config = getClassConfig();
    var details = config.details.map(function (item) {
      return "<dt>" + item[0] + "</dt><dd>" + item[1] + "</dd>";
    }).join("");

    var codes = config.showCodes
      ? codeRows.map(function (row) {
        return "<tr><td>" + escapeHtml(row[0]) + "</td><td>" + escapeHtml(row[1]) + "</td><td>" + escapeHtml(row[2]) + "</td><td>" + escapeHtml(row[3]) + "</td></tr>";
      }).join("")
      : '<tr class="empty-code-row"><td colspan="4"></td></tr>';

    return modalShell("details", [
      '<dl class="class-details">',
      details,
      "</dl>",
      "<div>",
      '<table class="codes-table"><thead><tr><th>Name</th><th>Username</th><th>Password</th><th>Company</th></tr></thead><tbody>',
      codes,
      "</tbody></table>",
      '<a class="print-btn" href="#">Print/ Save Codes</a>',
      "</div>"
    ].join(""), "dash-content");
  }

  function scoreActions(row) {
    var state = row[2];

    if (state === "notstarted") {
      return ["<td>Not Started</td>", "<td>-</td>"];
    }

    if (state === "noshow") {
      return ["<td>No Show</td>", "<td>-</td>"];
    }

    var release = state === "inprogress"
      ? '<a class="release-btn" href="#">Release</a>'
      : '<a class="release-btn" href="#">Release</a>';

    return [
      '<td><a class="score-btn" href="#" data-class-modal="report">Score Report</a> ' + release + '</td>',
      '<td><a class="release-btn certificate-download" href="' + certificatePdf + '" download>Download</a><div class="certificate-actions"><a class="mini-cert-btn" href="certificate-front.html" target="_blank" rel="noopener">Front</a><a class="mini-cert-btn" href="certificate-back.html" target="_blank" rel="noopener">Back</a></div></td>'
    ];
  }

  function renderScores() {
    if (currentClass === "open") {
      return modalShell("scores", [
        '<table class="scores-table class-scores-table active-class-scores"><thead><tr><th>Name</th><th>Skills Score</th><th>Knowledge Exam</th><th>Certificate</th></tr></thead><tbody>',
        '<tr><td>ALBULUSHI, ZAYID OMAR ALDAD</td><td><span class="score-edit-cell"><input class="score-input" aria-label="Skills Score" /> <a class="save-score" href="#">&#128190;</a></span></td><td>Not Started</td><td>-</td></tr>',
        "</tbody></table>"
      ].join(""), "dash-content scores-mode active-scores-mode");
    }

    if (currentClass === "notstarted") {
      return modalShell("scores", [
        '<table class="scores-table class-scores-table active-class-scores"><thead><tr><th>Name</th><th>Skills Score</th><th>Knowledge Exam</th><th>Certificate</th></tr></thead><tbody>',
        '<tr><td colspan="4" class="scores-empty-cell"></td></tr>',
        "</tbody></table>"
      ].join(""), "dash-content scores-mode active-scores-mode");
    }

    var rows = scoreRows.map(function (row) {
      var actions = scoreActions(row);
      var score = row[1] ? escapeHtml(row[1]) + ' <a class="edit-score" href="#"><span class="edit-score-icon" aria-hidden="true"></span></a>' : "-";
      return [
        "<tr>",
        "<td>" + escapeHtml(row[0]) + "</td>",
        "<td>" + score + "</td>",
        actions[0],
        actions[1],
        "</tr>"
      ].join("");
    }).join("");

    return modalShell("scores", [
      '<table class="scores-table class-scores-table"><thead><tr><th>Name</th><th>Skills Score</th><th>Knowledge Exam</th><th>Certificate</th></tr></thead><tbody>',
      rows,
      "</tbody></table>",
      '<a class="print-btn" href="#">Export Class Results</a>'
    ].join(""), "dash-content scores-mode");
  }

  function renderReport() {
    return [
      '<a class="modal-close" href="#" data-class-close>x</a>',
      '<div class="score-report-modal">',
      '<div class="report-top"><img src="assets/iadcLoginLgo.png" alt="IADC WellSharp" /><a class="print-btn" href="#">Print/ Save</a></div>',
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
      '<img src="assets/iadcLoginLgo.png" alt="IADC WellSharp" />',
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

  function openModal(type, classState) {
    if (classState) {
      currentClass = classState;
    }

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
    var release = event.target.closest(".release-btn:not(.certificate-download)");
    if (release) {
      event.preventDefault();
      release.textContent = "Released";
      release.classList.add("is-released");
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
        finishExamStateChange();
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
    openModal(trigger.getAttribute("data-class-modal"), trigger.getAttribute("data-class-state"));
  });
})();
