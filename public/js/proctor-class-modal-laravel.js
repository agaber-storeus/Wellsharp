(function () {
  var assets = window.wellsharpPrototypeAssets || { logo: "/images/iadcLoginLgo.png" };
  var classModalData = window.wellsharpClassModalData || {};
  var pendingExamAction = "start";
  var pendingExamControl = null;
  var pendingProctorId = "";

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

  function attrJson(value) {
    return escapeHtml(JSON.stringify(value === undefined ? null : value));
  }

  // Loads every enrolled Student's password for one Class in a single batch
  // request (never one request per Student), and caches the result on the
  // shared classModalData entry so switching between the Class Dashboard's
  // tabs doesn't re-fetch. Nothing here is ever written to localStorage,
  // sessionStorage, cookies, the URL, or a DOM data-* attribute - the fetched
  // values live only in this Alpine component's in-memory row state (and the
  // in-memory classModalData cache) for as long as the page is open.
  window.classRosterTable = function (rows, passwordsUrl, classId) {
    return {
      rows: rows || [],
      passwordsUrl: passwordsUrl || null,
      classId: classId || null,
      init: function () {
        var cache = this.classId && classModalData[this.classId] ? classModalData[this.classId].studentPasswords : null;
        if (cache) {
          this.applyPasswords(cache);
          return;
        }
        this.loadPasswords();
      },
      applyPasswords: function (byStudentId) {
        this.rows.forEach(function (row) {
          var hasValue = Object.prototype.hasOwnProperty.call(byStudentId, row.studentId) && byStudentId[row.studentId];
          row.password = hasValue ? byStudentId[row.studentId] : null;
          row.passwordState = hasValue ? "loaded" : "unavailable";
        });
      },
      loadPasswords: function () {
        if (!this.passwordsUrl) {
          this.applyPasswords({});
          return;
        }
        var self = this;

        fetch(this.passwordsUrl, {
          method: "POST",
          headers: {
            "Accept": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
          }
        }).then(function (response) {
          if (!response.ok) throw new Error("Failed to load passwords");
          return response.json();
        }).then(function (data) {
          var byStudentId = {};
          (data.students || []).forEach(function (entry) {
            byStudentId[entry.student_id] = entry.password;
          });
          self.applyPasswords(byStudentId);
          if (self.classId && classModalData[self.classId]) {
            classModalData[self.classId].studentPasswords = byStudentId;
          }
        }).catch(function () {
          self.applyPasswords({});
        });
      }
    };
  };

  // Applies a Skills Score save response onto a roster row. Shared by
  // saveScore() (the Alpine component's own, ephemeral row copy) and the
  // classModalData cache sync below, so both always receive identical
  // fields.
  function applyScoreRowResult(row, data) {
    row.skillsScore = data.skills_score;
    row.effectiveScore = data.effective_score;
    row.passed = data.passed;
    row.overridden = data.overridden;
    row.certificateDownloadUrl = data.certificate_download_url;
    row.certificateFrontUrl = data.certificate_front_url;
    row.certificateBackUrl = data.certificate_back_url;
    row.certificateNumber = data.certificate_number;
  }

  window.classScoresTable = function (rows) {
    return {
      rows: rows || [],
      startEditScore: function (row) {
        row.editing = true;
        row.draft = row.skillsScore === null || row.skillsScore === undefined ? "" : row.skillsScore;
        row.error = "";
      },
      saveScore: function (row) {
        if (!row.skillsScoreUrl || row.saving) return;
        var value = Number(row.draft);
        if (row.draft === "" || !isFinite(value) || value < 0 || value > 100) {
          row.error = "Enter a whole number from 0 to 100.";
          return;
        }

        var rounded = Math.round(value);
        row.saving = true;
        row.error = "";

        fetch(row.skillsScoreUrl, {
          method: "POST",
          headers: {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
          },
          body: JSON.stringify({ skills_score: rounded })
        }).then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, data: data };
          });
        }).then(function (result) {
          if (!result.ok) {
            var errors = result.data.errors || {};
            row.error = errors.skills_score ? errors.skills_score[0] : (result.data.message || "The Skills Score could not be saved.");
            return;
          }

          // The backend is the sole authority on pass/fail and certificate
          // state (see EffectiveScoreService / UpdateEnrollmentSkillsScoreAction) -
          // apply its full response onto the row so the Certificate cell,
          // which is already Alpine-bound to these same row.* properties,
          // updates reactively without any markup change or page reload.
          applyScoreRowResult(row, result.data);
          row.editing = false;

          // `row` here belongs to THIS Alpine component's own copy of `rows`,
          // parsed from a JSON snapshot of classModalData[...].scoreRows taken
          // when the modal was opened (see classScoresMarkup/attrJson) - it is
          // a disconnected object, not a reference into that cache. Closing
          // and reopening the Class Dashboard rebuilds the modal FROM that
          // cache (getClassConfig -> renderScores -> classScoresMarkup), so
          // without this, a save would appear to "revert" on reopen even
          // though the database already has the latest value. Keep the one
          // canonical cached row in sync instead of leaving it stale.
          if (currentClassId && classModalData[currentClassId] && classModalData[currentClassId].scoreRows) {
            var cachedRow = classModalData[currentClassId].scoreRows.find(function (candidate) {
              return candidate.skillsScoreUrl === row.skillsScoreUrl;
            });
            if (cachedRow) {
              applyScoreRowResult(cachedRow, result.data);
            }
          }
        }).catch(function () {
          row.error = "The Skills Score could not be saved. Try again.";
        }).finally(function () {
          row.saving = false;
        });
      },
      releaseAttempt: function (row) {
        if (!row.releaseUrl || row.releasing || row.releasedAt) return;
        row.releasing = true;

        fetch(row.releaseUrl, {
          method: "POST",
          headers: {
            "Accept": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
          }
        }).then(function (response) {
          if (!response.ok) throw new Error("Release failed");
          row.releasedAt = new Date().toISOString();
        }).catch(function () {
        }).finally(function () {
          row.releasing = false;
        });
      },
      openScoreReport: function (row) {
        if (row.reportUrl) openReport(row.reportUrl);
      }
    };
  };

  function classRosterMarkup(rows, passwordsUrl, classId) {
    return `
      <div x-data="classRosterTable(${attrJson(rows)}, ${attrJson(passwordsUrl)}, ${attrJson(classId)})">
        <table class="codes-table">
          <thead><tr>
            <th>Name</th>
            <th>Username</th>
            <th>Password</th>
            <th>Company</th>
          </tr></thead>
          <tbody>
            <template x-for="row in rows" :key="row.username + '-' + row.name">
              <tr>
                <td x-text="row.name"></td>
                <td x-text="row.username"></td>
                <td>
                  <span x-show="row.passwordState === 'loaded'" x-text="row.password"></span>
                  <span x-show="row.passwordState === 'unavailable'">Unavailable</span>
                  <span x-show="!row.passwordState">•••••</span>
                </td>
                <td x-text="row.company"></td>
              </tr>
            </template>
            <tr x-show="!rows.length"><td colspan="4" class="empty-code-row-cell">No enrolled trainees are stored for this Class.</td></tr>
          </tbody>
        </table>
      </div>
    `;
  }

  function classScoresMarkup(rows) {
    return `
      <div x-data="classScoresTable(${attrJson(rows)})">
        <table class="scores-table class-scores-table">
          <thead><tr>
            <th>Name</th>
            <th>Skills Score</th>
            <th>Knowledge Exam</th>
            <th>Certificate</th>
          </tr></thead>
          <tbody>
            <template x-for="row in rows" :key="(row.skillsScoreUrl || row.name)">
              <tr>
                <td x-text="row.name"></td>
                <td>
                  <template x-if="!row.editing">
                    <span class="score-edit-cell">
                      <span x-text="row.skillsScore === null || row.skillsScore === undefined ? '—' : row.skillsScore"></span>
                      <a href="#" class="edit-score" x-show="row.skillsScoreUrl" x-on:click.prevent="startEditScore(row)"><span class="edit-score-icon" aria-hidden="true"></span></a>
                    </span>
                  </template>
                  <template x-if="row.editing">
                    <span class="score-edit-cell">
                      <input class="score-input" type="number" min="0" max="100" x-model="row.draft" aria-label="Skills Score">
                      <a href="#" class="save-score" x-on:click.prevent="saveScore(row)">&#128190;</a>
                    </span>
                  </template>
                </td>
                <td>
                  <template x-if="row.state === 'notstarted'"><span>Not Started</span></template>
                  <template x-if="row.state === 'noshow'"><span>No Show</span></template>
                  <template x-if="row.state !== 'notstarted' && row.state !== 'noshow'">
                    <span>
                      <a class="score-btn" href="#" x-show="row.reportUrl" x-on:click.prevent="openScoreReport(row)">Score Report</a>
                      <span x-show="!row.reportUrl">Score Report</span>
                      <span class="release-btn is-released" x-show="row.releasedAt">Released</span>
                      <a class="release-btn" href="#" x-show="!row.releasedAt" x-on:click.prevent="releaseAttempt(row)" x-text="row.releasing ? 'Releasing...' : 'Release'"></a>
                    </span>
                  </template>
                </td>
                <td>
                  <template x-if="row.state !== 'notstarted' && row.state !== 'noshow' && row.certificateDownloadUrl">
                    <span>
                      <a class="release-btn certificate-download" x-bind:href="row.certificateDownloadUrl">Download</a>
                      <div class="certificate-actions">
                        <a class="mini-cert-btn" x-show="row.certificateFrontUrl" x-bind:href="row.certificateFrontUrl" target="_blank" rel="noopener">Front</a>
                        <a class="mini-cert-btn" x-show="row.certificateBackUrl" x-bind:href="row.certificateBackUrl" target="_blank" rel="noopener">Back</a>
                      </div>
                    </span>
                  </template>
                  <template x-if="!(row.state !== 'notstarted' && row.state !== 'noshow' && row.certificateDownloadUrl)">-</template>
                </td>
              </tr>
            </template>
            <tr x-show="!rows.length"><td colspan="4" class="scores-empty-cell">No trainees are enrolled in this Class.</td></tr>
          </tbody>
        </table>
      </div>
    `;
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
    var buttonText = isEnding ? "End Exam" : "Start Exam";
    var schedules = control && Array.isArray(control.scheduledFor) ? control.scheduledFor : [];
    var scheduledText = schedules.length
      ? schedules.map(function (schedule) {
        var range = [schedule.start, schedule.end].filter(Boolean).join(" to ");
        return escapeHtml(schedule.name + (range ? " (" + range + ")" : ""));
      }).join(", ")
      : "No linked Exam schedule";
    var controlNote = control
      ? "<p class=\"proctor-note\"><strong>This controls the linked Class.</strong> The Admin Exam schedule dates do not block this action after an authorized Proctor's ID is verified.</p>"
      : "";

    return [
      '<a class="modal-close" href="#" data-proctor-close>x</a>',
      '<h2>Enter Proctor\'s ID:</h2>',
      '<div class="proctor-code-row">',
      '<input id="proctorCodeInput" placeholder="Proctor ID" autocomplete="off" />',
      '<button class="tiny-green proctor-check-btn" type="button" data-proctor-check>Check</button>',
      '</div>',
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
    pendingExamControl = control;
    pendingExamAction = button.getAttribute("data-exam-action") || (control.status === "active" ? "end" : "start");
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
    var launch = document.getElementById("proctorLaunchButton");

    if (!launch) {
      return;
    }

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
  }

  function finishExamStateChange(responseData) {
    if (currentClassId && classModalData[currentClassId]) {
      var updatedStatus = responseData?.class?.status || (pendingExamAction === "start" ? "active" : "completed");
      var statusText = updatedStatus === "active" ? "Active" : (updatedStatus === "planned" ? "Not Started" : "Test Ended");
      var statusClass = updatedStatus === "active" ? "state-green" : (updatedStatus === "planned" ? "state-blue" : "state-ended");
      classModalData[currentClassId].examControl.status = updatedStatus;
      classModalData[currentClassId].details = classModalData[currentClassId].details.map(function (item) {
        return item[0] === "Class Status:" ? [item[0], statusText, statusClass] : item;
      });
    }

    closeProctorCheck();
    openModal("details", currentClass, currentClassId);
  }

  function controlClass() {
    var launch = document.getElementById("proctorLaunchButton");
    if (!pendingExamControl || !pendingProctorId || !launch) return;

    launch.disabled = true;
    launch.classList.add("disabled");
    setProctorMessage("error", pendingExamAction === "start" ? "Starting Class..." : "Ending Class...");

    postExamControl(pendingExamControl, pendingExamAction, pendingProctorId, function (result) {
      if (!result.ok) {
        var errors = result.data.errors || {};
        var message = errors.action ? errors.action[0] : (errors.proctor_id ? errors.proctor_id[0] : (result.data.message || "The Class could not be updated."));
        setProctorMessage("error", message);
        return;
      }

      finishExamStateChange(result.data);
    }, function () {
      setProctorMessage("error", "The Class could not be updated. Try again.");
    });
  }

  function postExamControl(control, action, proctorId, onSettled, onError) {
    var body = { action: action };
    if (proctorId) {
      body.proctor_id = proctorId;
    }

    return fetch(control.controlUrl, {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
      },
      body: JSON.stringify(body)
    }).then(function (response) {
      return response.json().then(function (data) {
        return { ok: response.ok, data: data };
      });
    }).then(onSettled).catch(onError);
  }

  // A Proctor controls the Class directly with no credential prompt.
  function controlClassAsProctor(action, control) {
    pendingExamControl = control;
    pendingExamAction = action;

    postExamControl(control, action, null, function (result) {
      if (!result.ok) {
        return;
      }

      finishExamStateChange(result.data);
    }, function () {
    });
  }

  function closeButtonMarkup() {
    return '<a class="modal-close" href="#" data-class-close>x</a>';
  }

  function modalShell(activeTab, body, extraClass) {
    var classContext = ' data-class-state="' + escapeHtml(currentClass) + '" data-class-id="' + escapeHtml(currentClassId || '') + '"';

    return closeButtonMarkup() + '<div class="modal-scroll-area">' + [
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
    ].join("") + '</div>';
  }

  var currentClass = "ended";
  var currentClassId = null;
  var currentReportData = null;

  function getClassConfig() {
    return classModalData[currentClassId] || { details: [["Class Status:", "Live Class data unavailable"]], codeRows: [], scoreRows: [], examControl: null };
  }

  function statusControlMarkup(examControl) {
    if (!examControl) {
      return "";
    }

    var canControl = examControl.status === "planned" || examControl.status === "active";
    if (!canControl) {
      return "";
    }

    var action = examControl.status === "active" ? "end" : "start";
    var label = examControl.status === "active" ? "Stop Exam" : "Start Exam";
    var buttonClass = action === "end" ? "tiny-red" : "tiny-blue";

    return ' <a href="#" class="' + buttonClass + ' exam-state-btn" data-exam-control data-exam-action="' + action + '">' + label + '</a>';
  }

  function renderDetails() {
    var config = getClassConfig();
    var examControl = config.examControl;
    var details = config.details.map(function (item) {
      var value = item[2] ? '<span class="' + item[2] + '"></span>' + escapeHtml(item[1]) : escapeHtml(item[1]);
      if (item[0] === "Class Status:") {
        value += statusControlMarkup(examControl);
      }
      return "<dt>" + escapeHtml(item[0]) + "</dt><dd>" + value + "</dd>";
    }).join("");

    return modalShell("details", [
      '<div class="class-detail-column"><dl class="class-details">',
      details,
      "</dl></div>",
      "<div>",
      classRosterMarkup(config.codeRows || [], config.studentPasswordsUrl || null, currentClassId),
      '<button class="print-btn" type="button" data-print-modal data-print-type="codes">Print/ Save Codes</button>',
      "</div>"
    ].join(""), "dash-content");
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
        return '<tr><td class="person-cell">' + escapeHtml(row.name) + '</td><td>' + escapeHtml(row.username) + '</td><td>' + escapeHtml(row.company) + '</td></tr>';
      }).join("") || '<tr><td colspan="3" class="empty-print">No enrolled trainees are stored for this Class.</td></tr>';
    }

    return (config.scoreRows || []).map(function (row) {
      var skillsScore = row.skillsScore !== null && row.skillsScore !== undefined ? escapeHtml(row.skillsScore) + "%" : "—";
      var state = printStateLabel(row.state);
      var certificate = row.certificateNumber ? "Issued · " + row.certificateNumber : "Not issued";
      var release = row.releasedAt ? "Released" : "Pending release";
      var attemptLabel = row.attemptNumber ? "Attempt " + escapeHtml(row.attemptNumber) + " · " + release : release;

      return '<tr><td class="person-cell">' + escapeHtml(row.name) + '</td><td class="score-cell">' + skillsScore + '</td><td><span class="status-chip ' + printStateClass(row.state) + '">' + state + '</span><small>' + attemptLabel + '</small></td><td>' + escapeHtml(certificate) + '</td></tr>';
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
    var headers = isCodes ? ["Name", "Username", "Company"] : ["Name", "Skills Score", "Knowledge Exam", "Certificate"];
    var generatedAt = new Date().toLocaleString();
    var tableClass = isCodes ? "codes-print-table" : "results-print-table";
    var documentMarkup = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' + title.replace(/&amp;/g, "&") + '</title><style>' +
      '@page{size:A4 landscape;margin:14mm}*{box-sizing:border-box}body{margin:0;background:#fff;color:#17364f;font:13px Arial,Helvetica,sans-serif}.print-sheet{max-width:1120px;margin:0 auto}.print-header{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;padding-bottom:18px;border-bottom:3px solid #e97825}.brand-lockup{display:flex;align-items:center;gap:14px}.brand-mark{width:58px;height:58px;object-fit:contain}.brand-name{color:#0e3554;font-size:19px;font-weight:700;letter-spacing:.04em}.brand-subtitle{margin-top:4px;color:#71808d;font-size:11px}.print-meta{text-align:right;color:#71808d;font-size:11px;line-height:1.5}.print-title{margin:24px 0 5px;color:#0e3554;font-size:25px}.print-subtitle{margin:0 0 18px;color:#71808d;font-size:13px}.print-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0 24px;margin-bottom:22px;padding:14px 16px;border:1px solid #d8e1e7;border-radius:8px;background:#f7fafb}.print-detail{display:grid;grid-template-columns:minmax(75px,auto) 1fr;gap:7px;padding:6px 0;border-bottom:1px solid #e5edf1}.print-detail dt{color:#71808d;font-weight:700}.print-detail dd{margin:0;color:#213547;overflow-wrap:anywhere}.print-table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border:1px solid #b9c7d2;border-radius:8px}.print-table th{padding:11px 12px;background:#0e3554;color:#fff;text-align:left;font-size:12px;letter-spacing:.04em}.print-table td{padding:11px 12px;border-top:1px solid #d8e1e7;vertical-align:top;line-height:1.3}.print-table tr:nth-child(even) td{background:#f2f6f8}.print-table tr:nth-child(odd) td{background:#fff}.print-table th:not(:last-child),.print-table td:not(:last-child){border-right:1px solid #d8e1e7}.person-cell{font-weight:700}.score-cell{font-size:16px;font-weight:700;color:#0e3554}.status-chip{display:inline-block;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap}.status-chip.success{background:#e7f7ee;color:#067647}.status-chip.danger{background:#fef0ee;color:#b42318}.status-chip.neutral{background:#edf2f7;color:#46566a}.print-table small{display:block;margin-top:5px;color:#71808d;font-size:10px}.empty-print{padding:28px!important;text-align:center;color:#71808d!important}.print-footer{display:flex;justify-content:space-between;gap:20px;margin-top:18px;padding-top:10px;border-top:1px solid #d8e1e7;color:#71808d;font-size:10px}.print-note{margin-top:13px;color:#71808d;font-size:10px}@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}.print-sheet{max-width:none}.no-print{display:none}}@media(max-width:800px){.print-detail-grid{grid-template-columns:1fr}.print-header{flex-direction:column}.print-meta{text-align:left}}' +
      '</style></head><body><main class="print-sheet"><header class="print-header"><div class="brand-lockup"><img class="brand-mark" src="' + escapeHtml(assets.logo) + '" alt="IADC WellSharp"><div><div class="brand-name">IADC WELLSHARP</div><div class="brand-subtitle">' + subtitle + '</div></div></div><div class="print-meta">Generated ' + escapeHtml(generatedAt) + '<br>WellSharp operational workspace</div></header><h1 class="print-title">' + title + '</h1><p class="print-subtitle">Class dashboard export</p><dl class="print-detail-grid">' + printDetails(config) + '</dl><table class="print-table ' + tableClass + '"><thead><tr>' + headers.map(function (header) { return '<th>' + header + '</th>'; }).join("") + '</tr></thead><tbody>' + printRows(type, config) + '</tbody></table><p class="print-note">This document was generated from the current Class dashboard data. Use the browser print dialog and choose “Save as PDF” to save a PDF copy.</p><footer class="print-footer"><span>WellSharp Class Dashboard</span><span>Confidential operational record</span></footer></main></body></html>';

    openPrintDocument(documentMarkup);
  }

  function openPrintDocument(documentMarkup) {
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

    return modalShell("scores", [
      classScoresMarkup(config.scoreRows || []),
      '<button class="print-btn" type="button" data-print-modal data-print-type="results">Export Class Results</button>'
    ].join(""), "dash-content scores-mode");
  }

  function renderReport(data) {
    var hasScore = data.score !== null && data.score !== undefined;
    var scoreLine = hasScore
      ? "You scored " + escapeHtml(data.score) + " percent on this knowledge assessment and, therefore, " + (data.passed ? "passed" : "did not pass") + " the course."
      : "This knowledge assessment has not been scored yet.";
    var topics = (data.topics || []).map(function (topic) {
      return "<li><u>" + escapeHtml(topic.title) + "</u><br><em>" + escapeHtml(topic.note) + "</em></li>";
    }).join("");

    return [
      '<a class="modal-close" href="#" data-class-close>x</a>',
      '<div class="score-report-modal">',
      '<div class="report-top"><img src="' + assets.logo + '" alt="IADC WellSharp" /><button class="print-btn" type="button" data-print-report>Print/ Save</button></div>',
      "<h2>Knowledge Assessment Report</h2>",
      '<div class="score-report-scroll">',
      '<dl class="report-fields">',
      "<dt>Name:</dt><dd>" + escapeHtml(data.name) + "</dd>",
      "<dt>Assessment:</dt><dd>" + escapeHtml(data.assessment) + "</dd>",
      "<dt>Stack:</dt><dd>" + escapeHtml(data.stack) + "</dd>",
      "<dt>Assessment Date:</dt><dd>" + escapeHtml(data.assessmentDate) + "</dd>",
      "<dt>Score:</dt><dd>" + (hasScore ? escapeHtml(data.score) + "%" : "Pending") + "</dd>",
      "</dl>",
      "<h3>Instructions</h3>",
      "<p>Thank you for completing the IADC Well Control Knowledge Assessment for the course. " + scoreLine + " If you passed your skills assessment you will be given your Certificate of Completion by your instructor, who will also review your missed questions with you.</p>",
      "<p>After your instructor reviews your exam results with you, you may choose to return to your computer to review each test question you missed on today's exam. To launch the review feature, log in using the same code you used at the beginning of the exam.</p>",
      "<p>Once you complete your review and you have received your Certificate, you are to log out of the testing system and may leave the testing center.</p>",
      "<h3>Topics for Review</h3>",
      topics ? ("<ul>" + topics + "</ul>") : "<p>No missed questions on this attempt.</p>",
      "</div>",
      "</div>"
    ].join("");
  }

  function reportErrorMarkup(message) {
    return '<a class="modal-close" href="#" data-class-close>x</a><div class="score-report-modal"><p class="proctor-note">' + escapeHtml(message) + "</p></div>";
  }

  function openReport(url) {
    if (!url) {
      return;
    }

    var modal = ensureModal();
    modal.className = "dashboard-modal shared-class-modal report-wrapper";
    modal.innerHTML = reportErrorMarkup("Loading report...");

    fetch(url, { headers: { "Accept": "application/json" } })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Unable to load report");
        }
        return response.json();
      })
      .then(function (data) {
        currentReportData = data;
        modal.innerHTML = renderReport(data);
      })
      .catch(function () {
        modal.innerHTML = reportErrorMarkup("The report could not be loaded. Try again.");
      });
  }

  function printReportDocument(data) {
    var topics = (data.topics || []).map(function (topic) {
      return '<div class="print-detail"><dt>' + escapeHtml(topic.title) + "</dt><dd>" + escapeHtml(topic.note) + "</dd></div>";
    }).join("") || '<div class="print-detail"><dd class="empty-print">No missed questions on this attempt.</dd></div>';
    var hasScore = data.score !== null && data.score !== undefined;
    var generatedAt = new Date().toLocaleString();
    var documentMarkup = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Knowledge Assessment Report</title><style>' +
      '@page{size:A4;margin:16mm}*{box-sizing:border-box}body{margin:0;background:#fff;color:#17364f;font:13px Arial,Helvetica,sans-serif}.print-sheet{max-width:820px;margin:0 auto}.print-header{display:flex;align-items:center;gap:14px;padding-bottom:18px;border-bottom:3px solid #e97825}.brand-mark{width:58px;height:58px;object-fit:contain}.brand-name{color:#0e3554;font-size:19px;font-weight:700;letter-spacing:.04em}.print-meta{margin-top:4px;color:#71808d;font-size:11px}.print-title{margin:24px 0 14px;color:#0e3554;font-size:25px}.print-detail-grid{display:grid;grid-template-columns:1fr;gap:0;margin-bottom:22px;padding:14px 16px;border:1px solid #d8e1e7;border-radius:8px;background:#f7fafb}.print-detail-grid.topics{grid-template-columns:1fr}.print-detail{display:grid;grid-template-columns:minmax(120px,auto) 1fr;gap:7px;padding:6px 0;border-bottom:1px solid #e5edf1}.print-detail dt{color:#71808d;font-weight:700}.print-detail dd{margin:0;color:#213547;overflow-wrap:anywhere}.print-detail dd.empty-print{color:#71808d}.print-note{margin-top:13px;color:#71808d;font-size:10px}@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}' +
      '</style></head><body><main class="print-sheet"><header class="print-header"><img class="brand-mark" src="' + escapeHtml(assets.logo) + '" alt="IADC WellSharp"><div><div class="brand-name">IADC WELLSHARP</div><div class="print-meta">Generated ' + escapeHtml(generatedAt) + '</div></div></header><h1 class="print-title">Knowledge Assessment Report</h1><dl class="print-detail-grid">' +
      '<div class="print-detail"><dt>Name</dt><dd>' + escapeHtml(data.name) + '</dd></div>' +
      '<div class="print-detail"><dt>Assessment</dt><dd>' + escapeHtml(data.assessment) + '</dd></div>' +
      '<div class="print-detail"><dt>Stack</dt><dd>' + escapeHtml(data.stack) + '</dd></div>' +
      '<div class="print-detail"><dt>Assessment Date</dt><dd>' + escapeHtml(data.assessmentDate) + '</dd></div>' +
      '<div class="print-detail"><dt>Score</dt><dd>' + (hasScore ? escapeHtml(data.score) + "%" : "Pending") + '</dd></div>' +
      '</dl><h1 class="print-title" style="font-size:18px;margin-top:0">Topics for Review</h1><dl class="print-detail-grid topics">' + topics + '</dl>' +
      '<p class="print-note">This document was generated from the current Class dashboard data. Use the browser print dialog and choose “Save as PDF” to save a PDF copy.</p></main></body></html>';

    openPrintDocument(documentMarkup);
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
    } else {
      modal.innerHTML = renderDetails();
    }
  }

  document.addEventListener("click", function (event) {
    var print = event.target.closest("[data-print-modal]");
    if (print) {
      event.preventDefault();
      printClassDocument(print.getAttribute("data-print-type") || "codes");
      return;
    }

    var printReport = event.target.closest("[data-print-report]");
    if (printReport) {
      event.preventDefault();
      if (currentReportData) {
        printReportDocument(currentReportData);
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
      var examControl = getClassConfig().examControl;
      if (window.wellsharpCurrentRole === "proctor") {
        controlClassAsProctor(action, examControl);
      } else {
        openProctorCheck(examControlButton, examControl);
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
        controlClass();
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
