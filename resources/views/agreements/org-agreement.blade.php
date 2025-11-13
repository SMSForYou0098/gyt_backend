<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Organizer Agreement</title>
  <style>
    @page { margin: 32px 38px 48px 38px; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; line-height: 1.45; color: #000; }
    h1, h2, h3 { margin: 0 0 8px; }
    h1 { font-size: 18px; text-align: center; text-transform: uppercase; }
    h2 { font-size: 14px; margin-top: 18px; }
    p { margin: 0 0 8px; text-align: justify; }
    .muted { color: #555; font-size: 11px; }
    .hr { border-top: 1px solid #000; margin: 12px 0; }
    .section { margin-top: 12px; }
    .table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .table td, .table th { border: 1px solid #333; padding: 6px 8px; vertical-align: top; }
    .small { font-size: 11px; }
    .footer { position: fixed; bottom: 20px; left: 0; right: 0; text-align: center; font-size: 10px; color: #666; }
    .page-break { page-break-after: always; }

    /* Watermark */
    @if(!empty($show_watermark))
    .watermark {
      position: fixed;
      top: 40%;
      left: 10%;
      transform: rotate(-30deg);
      opacity: 0.08;
      font-size: 64px;
      color: #000;
      z-index: -1;
      white-space: nowrap;
    }
    @endif
  </style>
</head>
<body>

@if(!empty($show_watermark))
  <div class="watermark">Private and Confidential</div>
@endif

<h1>AGREEMENT</h1>
<p class="muted">This agreement (“Agreement”) is made on this <strong>{{ $signing_date }}</strong> (“Signing Date”).</p>

<div class="section">
  <p><strong>Between:</strong></p>
  <p>
    <strong>Trava Get Your Ticket Pvt. Ltd.</strong>, a company incorporated under the Indian Companies Act, 2013,
    having its registered office located at 401, BLUE CRYSTAL COM, Opp. Vallabh Vidya Nagar Town Club,
    Vallabh Vidyanagar, Anand, Gujarat 388120 (hereinafter referred to as “Get Your Ticket”).
  </p>

  <p><strong>And</strong></p>

  <p>
    <strong>{{ $org_name }}</strong>, a {{ $org_type }} incorporated under applicable laws,
    having its registered address at <strong>{{ $org_reg_address }}</strong>
    (hereinafter referred to as “Event Organizer”).
  </p>

  <p class="muted">
    Get Your Ticket and Event Organizer shall hereinafter be individually referred to as a “Party” and collectively as the “Parties”.
  </p>
</div>

<div class="section">
  <h2>Recitals</h2>
  <p>
    The Event (as defined below) is the property of Event Organizer who shall organize the Event.
    Get Your Ticket is engaged in the business of rendering ticket booking services through various channels
    to enable customers to reserve/book tickets to various events. (Ref: draft structure) :contentReference[oaicite:2]{index=2}
  </p>
</div>

<div class="section">
  <h2>Definitions</h2>
  <p><strong>Event:</strong> {{ $event_name }} at the Venue: {{ $event_venue }}; Event Dates: {{ $event_dates }}.</p>
  <p><strong>Customers, Venue, Ticket, Confidential Information, IPR</strong> etc. shall have meanings per the draft. :contentReference[oaicite:3]{index=3}</p>
</div>

<div class="section">
  <h2>Appointment & Services</h2>
  <p>
    Event Organizer appoints Get Your Ticket to facilitate online booking of tickets through Platforms
    (website/app/approved third-parties) as per the draft. Exclusivity terms per draft. :contentReference[oaicite:4]{index=4}
  </p>
</div>

<div class="section">
  <h2>Responsibilities of Event Organizer</h2>
  <p>
    Licenses & permissions; safety; notifications for delay/cancellation; provide info; etc., as laid out in draft. :contentReference[oaicite:5]{index=5}
  </p>
</div>

<div class="section">
  <h2>Responsibilities of Get Your Ticket</h2>
  <p>Render services professionally and competently (per draft wording). :contentReference[oaicite:6]{index=6}</p>
</div>

<div class="section">
  <h2>Consideration & Payment Terms</h2>
  <p>Commission Fee: <strong>{{ $commission_percent }}%</strong> on total ticketing revenue.</p>
  <p>Payment Terms: <strong>{{ $payment_terms }}</strong>.</p>
  <p>Other terms per draft. :contentReference[oaicite:7]{index=7}</p>
</div>

<div class="section">
  <h2>Material Changes / Refunds</h2>
  <p>Cancellation/Refund handling and organizer reimbursement as detailed in draft. :contentReference[oaicite:8]{index=8}</p>
</div>

<div class="section">
  <h2>IPR, Liability, Confidentiality, Force Majeure</h2>
  <p>As per the draft clauses, summarized here. Full clause text can be pasted from your draft. :contentReference[oaicite:9]{index=9}</p>
</div>

<div class="section">
  <h2>Governing Law & Dispute Resolution</h2>
  <p>Laws of India; Arbitration in Mumbai; language English; courts at Mumbai—per draft. :contentReference[oaicite:10]{index=10}</p>
</div>

<div class="section">
  <h2>Term & Termination</h2>
  <p>Term: {{ $term_text }}; Termination conditions per draft. :contentReference[oaicite:11]{index=11}</p>
</div>

<div class="section">
  <h2>Entire Agreement; Amendments; Severability; Set-off</h2>
  <p>As per the draft. :contentReference[oaicite:12]{index=12}</p>
</div>

<div class="section">
  <h2>IN WITNESS WHEREOF</h2>
  <table class="table">
    <tr>
      <th style="width:50%">For Trava Get Your Ticket Pvt. Ltd.</th>
      <th>For: {{ $org_name }}</th>
    </tr>
    <tr>
      <td style="height:80px; vertical-align:bottom;">Authorised Signatory<br>Date: _____________</td>
      <td style="height:80px; vertical-align:bottom;">Authorised Signatory ({{ $org_signatory }})<br>Date: _____________</td>
    </tr>
  </table>
</div>

<div class="page-break"></div>

<h2>Schedule 1 – Particulars of the Event Organizer</h2>
<table class="table small">
  <tr><th>Name of company/proprietor/individual</th><td>{{ $org_name }}</td></tr>
  <tr><th>Type of company</th><td>{{ $org_type }}</td></tr>
  <tr><th>Registered office address</th><td>{{ $org_reg_address }}</td></tr>
  <tr><th>Name of authorised signatory</th><td>{{ $org_signatory }}</td></tr>
  <tr><th>GST number</th><td>{{ $gst }}</td></tr>
  <tr><th>PAN Number</th><td>{{ $pan }}</td></tr>
  <tr><th>Bank – Beneficiary</th><td>{{ $bank_beneficiary }}</td></tr>
  <tr><th>Account Number</th><td>{{ $bank_account }}</td></tr>
  <tr><th>IFSC Code</th><td>{{ $bank_ifsc }}</td></tr>
  <tr><th>Bank Name</th><td>{{ $bank_name }}</td></tr>
  <tr><th>Branch Name</th><td>{{ $bank_branch }}</td></tr>
</table>
<p class="muted">Layout & fields mapped to your draft Schedule 1. :contentReference[oaicite:13]{index=13}</p>

<div class="page-break"></div>

<h2>Schedule 2 – Commercial Arrangement</h2>
<table class="table small">
  <tr><th>Term</th><td>{{ $term_text }}</td></tr>
  <tr><th>Commission Fee</th><td>{{ $commission_percent }}% on ticketing revenue</td></tr>
  <tr><th>Payment Terms</th><td>{{ $payment_terms }}</td></tr>
</table>

<h3>Notices</h3>
<table class="table small">
  <tr>
    <th style="width:40%">To Get Your Ticket</th>
    <td>
      Attention: {{ $notice_to_name }}<br>
      Email: {{ $notice_to_email }}<br>
      Address: {{ $notice_to_address }}
    </td>
  </tr>
  <tr>
    <th>To Event Organizer</th>
    <td>
      Organiser name: {{ $org_name }}<br>
      Email: ____________________<br>
      Organisation: ____________________<br>
      Address: {{ $org_reg_address }}
    </td>
  </tr>
</table>
<p class="muted">Notices section structure per draft. :contentReference[oaicite:14]{index=14}</p>

<div class="footer">
  Trava Get Your Ticket Pvt. Ltd — Private & Confidential
</div>

</body>
</html>
