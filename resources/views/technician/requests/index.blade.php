@extends('layouts.app', ['title' => 'Job Request'])

@section('content')
<div class="page-header premium-page-header">
    <div>
        <h1>Job Request</h1>
        <p> </p>
    </div>
</div>

<div class="stats-grid admin-inbox-cards">
    <div class="stat-card premium-stat-card"><span>Total Assigned</span><strong>{{ $totalAssignedJobs ?? $jobs->total() }}</strong><small>All jobs assigned to this technician</small></div>
    <div class="stat-card premium-stat-card"><span>Pending / Active</span><strong>{{ $pendingJobs ?? 0 }}</strong><small>Jobs not yet completed</small></div>
    <div class="stat-card premium-stat-card"><span>Completed</span><strong>{{ $completedJobs ?? 0 }}</strong><small>Customer service report submitted</small></div>
    <div class="stat-card premium-stat-card"><span>Related Jobs</span><strong>{{ $relatedJobs ?? 0 }}</strong><small>Child jobs linked to another request</small></div>
</div>

<section class="panel premium-table-panel inbox-panel" style="margin-top:20px;">
    <div class="panel-head premium-table-head inbox-head">
        <div>
            <h3>Assigned Job Requests</h3>
            <p> </p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table inbox-table hierarchy-table technician-table-clean">
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th>Related Job</th>
                    <th>Technician Log</th>
                    <th>Report</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jobs as $job)
                    <tr class="{{ $job->related_request_id ? 'child-row premium-child-row' : '' }}">
                        <td class="table-primary-cell">
                            @if($job->related_request_id)
                                <span class="child-indent">↳ {{ $job->request_code }}</span>
                            @else
                                {{ $job->request_code }}
                            @endif
                        </td>
                        <td>{{ $job->full_name }}</td>
                        <td>{{ $job->requestType->name }}</td>
                        <td><span class="badge {{ $job->urgencyBadgeClass() }}">{{ $job->urgencyLabel() }}</span></td>
                        <td><span class="badge {{ $job->technicianStatusBadgeClass() }}">{{ $job->technicianStatusLabel() }}</span></td>
                        <td>{{ $job->relatedRequest?->request_code ?? '-' }}</td>
                        <td>
                            @if($job->technician_completed_at)
                                <span class="badge success">Locked</span>
                            @elseif($job->technician_log_started_at)
                                <div class="helper-text" style="margin-bottom:6px;">Started {{ $job->technicianLogStartedLabel() }}</div>
                                <button class="btn small danger" type="button" onclick="openLogModal('{{ $job->id }}', '{{ $job->request_code }}', '{{ $job->assignedTechnician?->name ?? auth()->user()->name }}', '{{ $job->technicianLogStartedAt()?->format('Y-m-d') }}', '{{ $job->technicianLogStartedAt()?->format('H:i') }}')">End</button>
                            @else
                                <form method="POST" action="{{ route('technician.job-requests.inspection-timer', $job) }}">
                                    @csrf
                                    <input type="hidden" name="timer_action" value="start">
                                    <button class="btn small success" type="submit">Start</button>
                                </form>
                            @endif
                        </td>
                        <td>@if($job->customer_service_report)<a class="btn small ghost" href="{{ route('technician.job-requests.report', $job) }}" target="_blank">Print</a>@else<span class="helper-text">Pending CSR</span>@endif</td>
                        <td><a class="btn small ghost" href="{{ route('technician.job-requests.show', $job) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9">No assigned job request yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">{{ $jobs->links() }}</div>
</section>

<div id="technician-log-modal" class="modal-shell" style="display:none;">
    <div class="modal-backdrop" onclick="closeLogModal()"></div>
    <div class="modal-card" style="max-width:960px; width:min(96vw, 960px);">
        <div class="panel-head" style="margin-bottom:14px;">
            <h3>Technician Daily Log</h3>
            <button class="btn tiny ghost" type="button" onclick="closeLogModal()">Close</button>
        </div>
        <form id="technician-log-form" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="timer_action" value="stop">
            <input type="hidden" name="ended_at" id="modal-ended-at">
            <div class="summary-stack">
                <div class="summary-card compact-summary"><strong>Job ID</strong><span id="log-job-code">-</span></div>
                <div class="summary-card compact-summary"><strong>Name of Technician</strong><span id="log-technician-name">-</span></div>
                <div class="summary-card compact-summary"><strong>Date</strong><span id="log-date">-</span></div>
                <div class="summary-card compact-summary"><strong>Time Start</strong><span id="log-start">-</span></div>
                <div class="summary-card compact-summary"><strong>Time End</strong><span id="log-end">-</span></div>
                <div class="summary-card compact-summary"><strong>Duration</strong><span id="log-duration">-</span></div>
            </div>
            <div class="two-col-inline" style="margin-top:16px; align-items:start;">
                <div>
                    <label>Remark</label>
                    <textarea name="remark" required placeholder="Enter technician remark"></textarea>
                </div>
                <div>
                    <label>Attachment ( Site Visit Image )</label>
                    <input type="file" name="attachments[]" multiple accept="image/*" required>
                </div>
            </div>
            <div class="signature-pad-shell" style="margin-top:16px;">
                <div>
                    <label>Person in Charge</label>
                    <canvas class="signature-pad" data-target="modal-person-in-charge"></canvas>
                    <input type="hidden" name="person_in_charge" id="modal-person-in-charge" required>
                    <button class="btn tiny ghost signature-clear" type="button">Clear</button>
                </div>
                <div>
                    <label>Verify By</label>
                    <canvas class="signature-pad" data-target="modal-verify-by"></canvas>
                    <input type="hidden" name="verify_by" id="modal-verify-by" required>
                    <input type="hidden" name="verify_by_signed_at" id="modal-verify-by-signed-at">
                    <div class="helper-text" id="verify-auto-submit-helper" style="margin-top:6px;"></div>
                    <button class="btn tiny ghost signature-clear" type="button">Clear</button>
                </div>
            </div>
            <div class="action-row" style="margin-top:16px;">
                <button class="btn danger" type="submit">Submit</button>
            </div>
        </form>
    </div>
</div>

<script>
const technicianLogModal = document.getElementById('technician-log-modal');
const technicianLogForm = document.getElementById('technician-log-form');
const verifySignedAtInput = document.getElementById('modal-verify-by-signed-at');
const verifyAutoSubmitHelper = document.getElementById('verify-auto-submit-helper');
const endedAtInput = document.getElementById('modal-ended-at');
let autoSubmitTimeout = null;
function pad(value){ return String(value).padStart(2,'0'); }
function nowIsoLocal(){ const d = new Date(); return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`; }
function nowLabel(){ return new Date().toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', hour12:true }); }
function formatDuration(startDate, endDate){
  let diff = Math.max(0, Math.floor((endDate - startDate) / 1000));
  const days = Math.floor(diff / 86400); diff %= 86400;
  const hours = Math.floor(diff / 3600); diff %= 3600;
  const minutes = Math.floor(diff / 60);
  const seconds = diff % 60;
  const parts = [];
  if (days > 0) parts.push(`${days}d`);
  if (hours > 0 || days > 0) parts.push(`${pad(hours)}h`);
  parts.push(`${pad(minutes)}m`);
  if (days === 0) parts.push(`${pad(seconds)}s`);
  return parts.join(' ');
}
function clearLogPad(canvas) {
  const ctx = canvas.getContext('2d');
  const ratio = window.devicePixelRatio || 1;
  const rect = canvas.getBoundingClientRect();
  const width = Math.max(320, Math.round(rect.width || canvas.parentElement?.clientWidth || 320));
  const height = Math.max(160, Math.round(rect.height || 160));
  ctx.setTransform(1,0,0,1,0,0);
  canvas.width = width * ratio;
  canvas.height = height * ratio;
  canvas.style.width = width + 'px';
  canvas.style.height = height + 'px';
  ctx.scale(ratio, ratio);
  ctx.lineWidth = 2.5;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.strokeStyle = '#0f172a';
}
function scheduleVerifyAutoSubmit() {
  if (!verifySignedAtInput || !verifySignedAtInput.value) return;
  clearTimeout(autoSubmitTimeout);
  if (verifyAutoSubmitHelper) verifyAutoSubmitHelper.textContent = `Signature detected at ${nowLabel()}. Form will auto submit in 2 minutes if you forget.`;
  autoSubmitTimeout = setTimeout(() => {
    if (technicianLogForm.dataset.submitted === '1') return;
    if (!verifySignedAtInput.value) return;
    technicianLogForm.dataset.submitted = '1';
    technicianLogForm.requestSubmit();
  }, 120000);
}
function bindSignaturePad(canvas) {
  if (!canvas || canvas.dataset.bound === '1') return;
  canvas.dataset.bound = '1';
  const target = document.getElementById(canvas.dataset.target);
  const isVerifyPad = target && target.id === 'modal-verify-by';
  const ctx = canvas.getContext('2d');
  let drawing = false;
  let hasStroke = false;
  const resize = () => clearLogPad(canvas);
  const point = (e) => {
    const rect = canvas.getBoundingClientRect();
    const source = e.touches ? e.touches[0] : e;
    return { x: source.clientX - rect.left, y: source.clientY - rect.top };
  };
  const start = (e) => {
    drawing = true;
    const p = point(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    e.preventDefault();
  };
  const move = (e) => {
    if (!drawing) return;
    const p = point(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    if (target) target.value = canvas.toDataURL('image/png');
    if (isVerifyPad && !hasStroke) {
      hasStroke = true;
      if (verifySignedAtInput && !verifySignedAtInput.value) {
        verifySignedAtInput.value = nowIsoLocal();
        scheduleVerifyAutoSubmit();
      }
    }
    e.preventDefault();
  };
  const stop = (e) => {
    if (!drawing) return;
    drawing = false;
    if (target) target.value = canvas.toDataURL('image/png');
    if (e) e.preventDefault();
  };
  resize();
  if (window.PointerEvent) {
    canvas.addEventListener('pointerdown', start);
    canvas.addEventListener('pointermove', move);
    window.addEventListener('pointerup', stop);
    window.addEventListener('pointercancel', stop);
  } else {
    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', stop);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    window.addEventListener('touchend', stop, { passive: false });
    window.addEventListener('touchcancel', stop, { passive: false });
  }
  window.addEventListener('resize', resize);
  canvas.parentElement.querySelector('.signature-clear')?.addEventListener('click', () => {
    if (isVerifyPad) {
      hasStroke = false;
      if (verifySignedAtInput) verifySignedAtInput.value = '';
      if (verifyAutoSubmitHelper) verifyAutoSubmitHelper.textContent = '';
      clearTimeout(autoSubmitTimeout);
    }
    clearLogPad(canvas);
    if (target) target.value = '';
  });
}
function openLogModal(jobId, jobCode, technicianName, startDate, startTime){
  const start = new Date(`${startDate}T${startTime}:00`);
  const end = new Date();
  const endedAtValue = nowIsoLocal();
  technicianLogForm.reset(); technicianLogForm.dataset.submitted = ''; if (verifySignedAtInput) verifySignedAtInput.value=''; if (verifyAutoSubmitHelper) verifyAutoSubmitHelper.textContent=''; if (endedAtInput) endedAtInput.value = endedAtValue; clearTimeout(autoSubmitTimeout);
  technicianLogForm.action = `/technician/job-requests/${jobId}/inspection-timer`;
  document.getElementById('log-job-code').textContent = jobCode;
  document.getElementById('log-technician-name').textContent = technicianName;
  document.getElementById('log-date').textContent = end.toLocaleDateString('en-GB');
  document.getElementById('log-start').textContent = startTime;
  document.getElementById('log-end').textContent = `${pad(end.getHours())}:${pad(end.getMinutes())}:${pad(end.getSeconds())}`;
  document.getElementById('log-duration').textContent = formatDuration(start, end);
  technicianLogModal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  requestAnimationFrame(() => {
    document.querySelectorAll('#technician-log-modal .signature-pad').forEach((canvas) => {
      bindSignaturePad(canvas);
      clearLogPad(canvas);
      const target = document.getElementById(canvas.dataset.target);
      if (target) target.value = '';
    });
  });
}
function closeLogModal(){ clearTimeout(autoSubmitTimeout); technicianLogModal.style.display = 'none'; document.body.style.overflow = ''; }
technicianLogForm?.addEventListener('submit', () => { technicianLogForm.dataset.submitted = '1'; clearTimeout(autoSubmitTimeout); });
document.querySelectorAll('.signature-pad').forEach(bindSignaturePad);
</script>
@endsection
