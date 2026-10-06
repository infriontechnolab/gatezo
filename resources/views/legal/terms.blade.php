@extends('layouts.public', ['title' => 'Terms of use · Gatezo', 'noindex' => false, 'wide' => true])
@section('content')
@component('legal._page', ['heading' => 'Terms of use', 'updated' => '5 October 2026'])

<p>These terms apply to anyone who uses Gatezo: organizers and their team, volunteers, stall owners, and people who register for an event. By using Gatezo you agree to them. How we handle personal data is in the <a href="{{ route('privacy') }}">privacy policy</a>.</p>

<h2>Your account</h2>
<ul>
    <li>Give accurate details when you sign up, and keep your password to yourself.</li>
    <li>You are responsible for what happens under your account, including what the team members you invite and the volunteers you give your event code or links to do with your event.</li>
    <li>If you think someone else has your password or event code, change it: reset the password, or create a new volunteer code from the dashboard.</li>
</ul>

<h2>If you organize an event</h2>
<p>You decide what is collected from the people who register for your event, so you are responsible for it. In particular:</p>
<ul>
    <li>Only add people's details you are allowed to use, and tell them what you collect and why.</li>
    <li>Only message people who agreed to hear from you, and stop when they ask. Gatezo's export of WhatsApp numbers only includes people who said yes; keep it that way in whatever tool you send from.</li>
    <li>Act on requests from people who want to see, correct or delete their data. We will help.</li>
    <li>Gatezo's headcount, capacity warnings and gate checks help you run the event; they are not a safety system. Crowd safety, capacity limits, and who is let in remain your decisions and your responsibility.</li>
</ul>

<h2>What you may not do</h2>
<ul>
    <li>Use Gatezo for anything illegal, or for an event that is.</li>
    <li>Send spam, or use the data in Gatezo for anything other than your events.</li>
    <li>Forge or copy passes, try to get into other organizers' events or data, or interfere with the service.</li>
    <li>Copy data out of Gatezo by automated means, or resell the service.</li>
</ul>

<h2>Plans and payment</h2>
<ul>
    <li>The free plan and each paid plan come with the limits shown on the Plans page.</li>
    <li>A paid plan covers the period you paid for and ends on its last day; it does not renew by itself. Payment is by UPI or bank transfer, and the plan starts when we confirm the payment.</li>
    <li>We may change prices and limits for the future. A period you have already paid for keeps its price and limits.</li>
    <li>If your plan ends, your events and data stay; only what the free plan doesn't allow (such as creating more events) stops.</li>
</ul>

<h2>Your data</h2>
<p>The data you put into Gatezo stays yours. You allow us to store and process it only to run Gatezo for you, as the privacy policy describes. You can export your attendee lists, leads and reports at any time.</p>
<p>The public demo event is shared by everyone who opens it and is reset every night. Don't enter real people's details there.</p>

<h2>The service</h2>
<ul>
    <li>We work to keep Gatezo running, and the scanner is built to keep working without signal. We can't promise the service will never be interrupted, and we may change or improve features over time.</li>
    <li>Gatezo is provided as it is. To the extent the law allows, we are not liable for indirect losses (such as lost revenue, or the consequences of an event not going as planned), and our total liability to you is limited to what you paid us in the 12 months before the claim.</li>
</ul>

<h2>Ending</h2>
<ul>
    <li>You can stop using Gatezo at any time. To close your account and delete your data, write to us.</li>
    <li>We may suspend or close an account that breaks these terms or puts other people at risk. Where we can, we will tell you first and give you a chance to export your data.</li>
</ul>

<h2>Changes</h2>
<p>If these terms change, we will update the date at the top, and tell organizers by email about changes that matter. Using Gatezo after a change means you accept the new terms.</p>

@endcomponent
@endsection
