<?php

namespace Database\Seeders\Demo;

use Database\Seeders\DemoSeeder;

/**
 * Content for the six knowledge-base PDFs that {@see DemoSeeder} renders and stores.
 *
 * These are not filler. The demo's AI nodes are grounded in these documents, so their content is written
 * to be internally consistent with the prompts that cite them — change one and check the other:
 *
 *  - the capability matrix carries the "on-premise or regulated data is never simple" rule and the list of
 *    domains with no bench, which is what makes the `ai-classifier` return `complex` / `unclear`;
 *  - the rate card carries the 12% discount authority threshold quoted in the commercial pricing task;
 *  - the phase model defines the exact phase/person-days/rate-band table the child workflow asks for;
 *  - the proposal template defines the exact section list the `draft-proposal` node asks for.
 *
 * Every company, client, person, price and certificate number below is invented.
 */
class DemoDocumentLibrary
{
    /**
     * Document key => metadata. `type` must match a seeded DocumentType name.
     *
     * @return array<string, array{title: string, type: string, reference: string, classification: string, body: string}>
     */
    public static function documents(): array
    {
        return [
            'capabilityMatrix' => [
                'title' => 'Company delivery capability matrix',
                'type' => 'Procedure',
                'reference' => 'PRE-001',
                'classification' => 'Internal',
                'body' => self::capabilityMatrix(),
            ],
            'sowLibrary' => [
                'title' => 'Past SOW library 2024-2026',
                'type' => 'Procedure',
                'reference' => 'PRE-002',
                'classification' => 'Internal',
                'body' => self::sowLibrary(),
            ],
            'proposalTemplate' => [
                'title' => 'Company proposal template v4',
                'type' => 'Procedure',
                'reference' => 'PRE-003',
                'classification' => 'Internal',
                'body' => self::proposalTemplate(),
            ],
            'rateCard' => [
                'title' => 'Rate card 2026',
                'type' => 'Policy',
                'reference' => 'FIN-014',
                'classification' => 'Confidential',
                'body' => self::rateCard(),
            ],
            'phaseModel' => [
                'title' => 'Standard phase model',
                'type' => 'Procedure',
                'reference' => 'DEL-007',
                'classification' => 'Internal',
                'body' => self::phaseModel(),
            ],
            'securityWhitepaper' => [
                'title' => 'Security and compliance whitepaper',
                'type' => 'Policy',
                'reference' => 'SEC-002',
                'classification' => 'Public',
                'body' => self::securityWhitepaper(),
            ],
        ];
    }

    /**
     * Wraps a document body in the shared page shell (cover header, running footer, print styles).
     */
    public static function render(string $key): string
    {
        $document = self::documents()[$key] ?? throw new \InvalidArgumentException("Unknown demo document '{$key}'.");

        $title = e($document['title']);
        $reference = e($document['reference']);
        $classification = e($document['classification']);
        $type = e($document['type']);
        $styles = self::styles();

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="utf-8"><title>{$title}</title><style>{$styles}</style></head>
        <body>
            <div class="running-footer">
                <span class="footer-left">Company &middot; {$reference} &middot; {$classification}</span>
                <span class="footer-right">Uncontrolled when printed</span>
            </div>

            <div class="masthead">
                <div class="brand">COMPANY</div>
                <div class="meta">
                    <span class="pill">{$type}</span>
                    <span class="ref">{$reference}</span>
                </div>
            </div>

            <h1>{$title}</h1>
            <div class="doc-strip">
                <span><strong>Owner:</strong> Pre-Sales &amp; Delivery</span>
                <span><strong>Effective:</strong> 1 January 2026</span>
                <span><strong>Review:</strong> 31 December 2026</span>
                <span><strong>Classification:</strong> {$classification}</span>
            </div>

            {$document['body']}
        </body>
        </html>
        HTML;
    }

    private static function styles(): string
    {
        return <<<'CSS'
        @page { margin: 20mm 16mm 22mm 16mm; }
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5pt;
            line-height: 1.5;
            color: #1f2328;
            margin: 0;
        }
        .running-footer {
            position: fixed;
            bottom: -14mm; left: 0; right: 0;
            font-size: 7.5pt;
            color: #6b7280;
            border-top: 0.5pt solid #d8dbe0;
            padding-top: 3mm;
        }
        .footer-right { float: right; }
        .masthead {
            border-bottom: 2pt solid #0f6e56;
            padding-bottom: 3mm;
            margin-bottom: 6mm;
        }
        .brand {
            display: inline-block;
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 3pt;
            color: #0f6e56;
        }
        .meta { float: right; font-size: 8pt; color: #6b7280; padding-top: 2mm; }
        .pill {
            background: #e1f5ee; color: #04342c;
            border-radius: 2mm; padding: 1mm 2.5mm; margin-right: 2mm;
        }
        h1 { font-size: 17pt; margin: 0 0 3mm 0; color: #04342c; }
        h2 {
            font-size: 11pt; margin: 7mm 0 2.5mm 0; color: #0f6e56;
            border-bottom: 0.5pt solid #d8dbe0; padding-bottom: 1.5mm;
        }
        h3 { font-size: 9.5pt; margin: 5mm 0 1.5mm 0; color: #1f2328; }
        p { margin: 0 0 2.5mm 0; }
        .doc-strip {
            background: #f6f7f8; border: 0.5pt solid #e2e5e9;
            padding: 2.5mm 3mm; font-size: 8pt; color: #4b5563; margin-bottom: 5mm;
        }
        .doc-strip span { margin-right: 6mm; }
        .lede { font-size: 10pt; color: #374151; margin-bottom: 4mm; }
        table { width: 100%; border-collapse: collapse; margin: 2mm 0 4mm 0; font-size: 8.5pt; }
        th {
            background: #04342c; color: #ffffff; text-align: left;
            padding: 2mm 2.5mm; font-weight: bold; font-size: 8pt;
        }
        td { padding: 1.8mm 2.5mm; border-bottom: 0.5pt solid #e2e5e9; vertical-align: top; }
        tr.alt td { background: #f8f9fa; }
        td.num, th.num { text-align: right; }
        .tag { font-size: 7.5pt; padding: 0.6mm 1.8mm; border-radius: 1.5mm; white-space: nowrap; }
        .tag-simple { background: #e1f5ee; color: #04342c; }
        .tag-standard { background: #eef2ff; color: #26215c; }
        .tag-complex { background: #faece7; color: #7a2d10; }
        .tag-none { background: #f1efe8; color: #4b4a46; }
        .callout {
            border-left: 2.5pt solid #0f6e56; background: #f4faf8;
            padding: 3mm 3.5mm; margin: 3mm 0 4mm 0;
        }
        .callout-warn { border-left-color: #b45309; background: #fdf7ec; }
        .callout p:last-child { margin-bottom: 0; }
        ul, ol { margin: 0 0 3mm 0; padding-left: 5mm; }
        li { margin-bottom: 1.2mm; }
        code {
            font-family: "DejaVu Sans Mono", monospace; font-size: 8pt;
            background: #f1efe8; padding: 0.3mm 1.2mm;
        }
        .small { font-size: 8pt; color: #6b7280; }
        .page-break { page-break-before: always; }
        CSS;
    }

    /**
     * Grounds the `ai-classifier` triage node. The complexity floors and the "no bench" list below are
     * what make an on-premise regulated deal come back as `complex`, and an off-matrix domain `unclear`.
     */
    private static function capabilityMatrix(): string
    {
        return <<<'HTML'
        <p class="lede">What Company can deliver today, how deep the bench is, and the minimum complexity
        band each capability forces onto an opportunity. Pre-sales triage scores every inbound RFP against
        this matrix before a solution architect is engaged.</p>

        <h2>1. How to use this matrix</h2>
        <p>Score the opportunity against every capability the request touches. The opportunity takes the
        <strong>highest</strong> complexity floor of any capability it needs — a deal is never simpler than
        its hardest component. If the request depends on a capability that is not listed in section 2 or 3,
        it is <strong>unclear</strong>, not complex: an unknown is a scoping problem, not a difficulty rating.</p>

        <h2>2. Capability bench</h2>
        <table>
            <thead><tr>
                <th>Capability area</th><th class="num">Bench (FTE)</th><th>Depth</th>
                <th class="num">Lead time</th><th>Complexity floor</th>
            </tr></thead>
            <tbody>
                <tr><td>Web &amp; customer portal engineering</td><td class="num">14</td><td>Deep</td><td class="num">2 weeks</td><td><span class="tag tag-simple">simple</span></td></tr>
                <tr class="alt"><td>API &amp; integration (REST, GraphQL, events)</td><td class="num">9</td><td>Deep</td><td class="num">2 weeks</td><td><span class="tag tag-simple">simple</span></td></tr>
                <tr><td>Cloud delivery (AWS, Azure)</td><td class="num">11</td><td>Deep</td><td class="num">2 weeks</td><td><span class="tag tag-simple">simple</span></td></tr>
                <tr class="alt"><td>Mobile (iOS, Android)</td><td class="num">7</td><td>Deep</td><td class="num">3 weeks</td><td><span class="tag tag-simple">simple</span></td></tr>
                <tr><td>Data platform &amp; analytics</td><td class="num">6</td><td>Deep</td><td class="num">4 weeks</td><td><span class="tag tag-standard">standard</span></td></tr>
                <tr class="alt"><td>SAP integration (S/4HANA, ECC)</td><td class="num">3</td><td>Moderate</td><td class="num">6 weeks</td><td><span class="tag tag-standard">standard</span></td></tr>
                <tr><td>Custom / legacy ERP integration</td><td class="num">4</td><td>Moderate</td><td class="num">6 weeks</td><td><span class="tag tag-standard">standard</span></td></tr>
                <tr class="alt"><td>Telecom BSS / OSS</td><td class="num">4</td><td>Moderate</td><td class="num">8 weeks</td><td><span class="tag tag-standard">standard</span></td></tr>
                <tr><td>Identity &amp; access management</td><td class="num">5</td><td>Moderate</td><td class="num">4 weeks</td><td><span class="tag tag-standard">standard</span></td></tr>
                <tr class="alt"><td><strong>On-premise deployment</strong></td><td class="num">3</td><td>Limited</td><td class="num">10 weeks</td><td><span class="tag tag-complex">complex</span></td></tr>
                <tr><td><strong>Regulated data (PII / PCI / PHI)</strong></td><td class="num">2</td><td>Limited</td><td class="num">12 weeks</td><td><span class="tag tag-complex">complex</span></td></tr>
                <tr class="alt"><td>Air-gapped / sovereign hosting</td><td class="num">1</td><td>Limited</td><td class="num">16 weeks</td><td><span class="tag tag-complex">complex</span></td></tr>
            </tbody>
        </table>

        <div class="callout callout-warn">
            <p><strong>Hard rule.</strong> An on-premise deployment or regulated data is <strong>never</strong>
            classified as <code>simple</code>, regardless of budget or apparent scope. Both carry a limited
            bench, a long lead time and a mandatory expert review before any number reaches a client.</p>
        </div>

        <h2>3. Capabilities we do not hold</h2>
        <p>We have no bench, no reference delivery and no partner arrangement for the following. An RFP that
        depends on any of them must be classified <code>unclear</code> and scoped by hand — do not estimate
        it from the rate card.</p>
        <table>
            <thead><tr><th>Domain</th><th>Status</th><th>Nearest adjacent capability</th></tr></thead>
            <tbody>
                <tr><td>Robotics, OT and SCADA control systems</td><td><span class="tag tag-none">no bench</span></td><td>Data platform (telemetry only)</td></tr>
                <tr class="alt"><td>Blockchain / distributed ledger</td><td><span class="tag tag-none">no bench</span></td><td>None</td></tr>
                <tr><td>Embedded firmware</td><td><span class="tag tag-none">no bench</span></td><td>None</td></tr>
                <tr class="alt"><td>AR / VR and spatial computing</td><td><span class="tag tag-none">no bench</span></td><td>Mobile</td></tr>
                <tr><td>Real-time game engines</td><td><span class="tag tag-none">no bench</span></td><td>None</td></tr>
                <tr class="alt"><td>Medical device software (IEC 62304)</td><td><span class="tag tag-none">no bench</span></td><td>Regulated data</td></tr>
            </tbody>
        </table>

        <h2>4. Triage bands</h2>
        <table>
            <thead><tr><th>Band</th><th>Meaning</th><th>Pre-sales route</th></tr></thead>
            <tbody>
                <tr><td><span class="tag tag-simple">simple</span></td><td>Single capability, deep bench, cloud deployment, no regulated data.</td><td>Standard estimation pack, no architect required.</td></tr>
                <tr class="alt"><td><span class="tag tag-standard">standard</span></td><td>Two or more capabilities, or one moderate-depth capability.</td><td>Parallel architect estimate, commercial pricing and capacity check.</td></tr>
                <tr><td><span class="tag tag-complex">complex</span></td><td>On-premise, regulated data, sovereign hosting, or three or more moderate capabilities.</td><td>Expert review path designed per deal by the pre-sales lead.</td></tr>
                <tr class="alt"><td><span class="tag tag-none">unclear</span></td><td>Depends on a domain absent from this matrix.</td><td>Manual scoping, then a capability matrix update.</td></tr>
            </tbody>
        </table>

        <h2>5. Confidence guidance</h2>
        <p>Return a confidence below <code>0.6</code> when the request is too thin to place against this
        matrix — a two-line summary, no deployment model, or no indication of data sensitivity. Low
        confidence disqualifies the opportunity from automatic routing and sends it to a human.</p>
        <p class="small">Reviewed quarterly by the Head of Engineering. Bench figures as at 1 January 2026.</p>
        HTML;
    }

    /**
     * Reference deliveries the AI nodes cite for comparable effort and pricing.
     */
    private static function sowLibrary(): string
    {
        return <<<'HTML'
        <p class="lede">Index of signed statements of work from January 2024 to date, with actual delivered
        effort against the original estimate. Use it to sanity-check a new estimate against a comparable
        delivery before it goes to a client — never to copy a price across.</p>

        <h2>1. Signed engagements</h2>
        <table>
            <thead><tr>
                <th>Ref</th><th>Client</th><th>Industry</th><th>Deployment</th>
                <th class="num">Est. (pd)</th><th class="num">Actual (pd)</th><th class="num">Value (EUR)</th>
            </tr></thead>
            <tbody>
                <tr><td>SOW-2024-011</td><td>Meridian Bank</td><td>Banking</td><td>Cloud</td><td class="num">180</td><td class="num">196</td><td class="num">198,000</td></tr>
                <tr class="alt"><td>SOW-2024-019</td><td>Halden Energy</td><td>Utilities</td><td>Hybrid</td><td class="num">240</td><td class="num">288</td><td class="num">276,000</td></tr>
                <tr><td>SOW-2024-027</td><td>Verda Retail Group</td><td>Retail</td><td>Cloud</td><td class="num">95</td><td class="num">99</td><td class="num">104,500</td></tr>
                <tr class="alt"><td>SOW-2025-004</td><td>Kestrel Telecom</td><td>Telecom</td><td>Hybrid</td><td class="num">310</td><td class="num">341</td><td class="num">372,000</td></tr>
                <tr><td>SOW-2025-008</td><td>Northgate Health Trust</td><td>Healthcare</td><td>On-premise</td><td class="num">265</td><td class="num">352</td><td class="num">318,000</td></tr>
                <tr class="alt"><td>SOW-2025-015</td><td>Aurelia Insurance</td><td>Insurance</td><td>Cloud</td><td class="num">140</td><td class="num">147</td><td class="num">154,000</td></tr>
                <tr><td>SOW-2025-022</td><td>Port of Vellmar</td><td>Public sector</td><td>On-premise</td><td class="num">200</td><td class="num">274</td><td class="num">240,000</td></tr>
                <tr class="alt"><td>SOW-2025-031</td><td>Lumen Logistics</td><td>Logistics</td><td>Cloud</td><td class="num">120</td><td class="num">126</td><td class="num">132,000</td></tr>
                <tr><td>SOW-2026-002</td><td>Sundby Mobil</td><td>Telecom</td><td>Cloud</td><td class="num">165</td><td class="num">171</td><td class="num">181,500</td></tr>
                <tr class="alt"><td>SOW-2026-006</td><td>Calder Pension Fund</td><td>Financial services</td><td>On-premise</td><td class="num">225</td><td class="num">297</td><td class="num">270,000</td></tr>
            </tbody>
        </table>

        <h2>2. Estimate accuracy by deployment model</h2>
        <p>Overrun is not distributed evenly. On-premise deliveries have overrun on every engagement we have
        signed, driven by environment access, change windows and third-party sign-off rather than by build effort.</p>
        <table>
            <thead><tr><th>Deployment</th><th class="num">Engagements</th><th class="num">Mean overrun</th><th class="num">Worst case</th><th>Recommended contingency</th></tr></thead>
            <tbody>
                <tr><td>Cloud (SaaS)</td><td class="num">5</td><td class="num">+4.6%</td><td class="num">+8.9%</td><td class="num">10%</td></tr>
                <tr class="alt"><td>Hybrid</td><td class="num">2</td><td class="num">+15.0%</td><td class="num">+20.0%</td><td class="num">20%</td></tr>
                <tr><td>On-premise</td><td class="num">3</td><td class="num">+33.1%</td><td class="num">+37.0%</td><td class="num">35%</td></tr>
            </tbody>
        </table>

        <div class="callout">
            <p><strong>Estimating note.</strong> Where an opportunity is on-premise, apply the 35% contingency
            to the build phases before pricing, and state it explicitly in the proposal's assumptions section.
            A client who is surprised by it later has been mis-sold.</p>
        </div>

        <h2>3. Closest comparables by profile</h2>
        <table>
            <thead><tr><th>If the opportunity looks like&hellip;</th><th>Use as comparable</th><th>Why</th></tr></thead>
            <tbody>
                <tr><td>Telecom, customer portal, cloud</td><td>SOW-2026-002 &middot; Sundby Mobil</td><td>Same stack, same integration count, closed within 4% of estimate.</td></tr>
                <tr class="alt"><td>Telecom, BSS integration, hybrid</td><td>SOW-2025-004 &middot; Kestrel Telecom</td><td>Nearest BSS/OSS delivery; note the 10% overrun on integration.</td></tr>
                <tr><td>Regulated data, on-premise</td><td>SOW-2025-008 &middot; Northgate Health Trust</td><td>Only PHI delivery to date; security review alone ran 26 person-days.</td></tr>
                <tr class="alt"><td>Public sector, on-premise, procurement-led</td><td>SOW-2025-022 &middot; Port of Vellmar</td><td>Long sign-off chain; 37% overrun was schedule, not scope.</td></tr>
                <tr><td>Financial services, on-premise</td><td>SOW-2026-006 &middot; Calder Pension Fund</td><td>Most recent regulated on-prem delivery; current control set applies.</td></tr>
            </tbody>
        </table>
        <p class="small">Values are contract value excluding expenses and third-party licences. Effort is
        Company-delivered person-days only.</p>
        HTML;
    }

    /**
     * Defines the exact section list the `draft-proposal` ai-generator node is told to follow.
     */
    private static function proposalTemplate(): string
    {
        return <<<'HTML'
        <p class="lede">The mandatory structure for every client-facing proposal. Version 4 replaces the
        2024 template: the commercial summary now precedes next steps, and the assumptions section is no
        longer optional. Proposals that deviate from this structure are returned at manager approval.</p>

        <h2>1. Section structure</h2>
        <table>
            <thead><tr><th class="num">#</th><th>Section</th><th>Must contain</th><th class="num">Length</th></tr></thead>
            <tbody>
                <tr><td class="num">1</td><td><strong>Executive summary</strong></td><td>The client's problem in their own words, the outcome we are proposing, the headline investment and the headline timeline. No technology names.</td><td class="num">150&ndash;250 w</td></tr>
                <tr class="alt"><td class="num">2</td><td><strong>Proposed solution</strong></td><td>The solution shape, the components, how it integrates with what they already run. One diagram maximum.</td><td class="num">400&ndash;700 w</td></tr>
                <tr><td class="num">3</td><td><strong>Scope and assumptions</strong></td><td>What is in scope, what is explicitly out, and every assumption the estimate depends on. Contingency stated as a number.</td><td class="num">300&ndash;500 w</td></tr>
                <tr class="alt"><td class="num">4</td><td><strong>Delivery plan with phases</strong></td><td>Phase table per the standard phase model, with person-days, elapsed weeks and client responsibilities per phase.</td><td class="num">250&ndash;400 w</td></tr>
                <tr><td class="num">5</td><td><strong>Commercial summary</strong></td><td>Investment by phase, payment terms, what triggers each invoice, validity period. Figures only from the approved estimate.</td><td class="num">150&ndash;300 w</td></tr>
                <tr class="alt"><td class="num">6</td><td><strong>Next steps</strong></td><td>Three or fewer concrete actions with named owners and dates.</td><td class="num">80&ndash;150 w</td></tr>
            </tbody>
        </table>

        <h2>2. Non-negotiable rules</h2>
        <div class="callout callout-warn">
            <p><strong>Never invent a figure.</strong> Every number in a proposal — effort, price, discount,
            date — must trace to the approved estimate or the rate card. If a number is not available, write
            "to be confirmed at contracting" rather than estimating in the document.</p>
        </div>
        <ul>
            <li><strong>Assumptions are load-bearing.</strong> Anything that would change the price if it turned out to be false belongs in section 3, in plain language.</li>
            <li><strong>No unqualified superlatives.</strong> "Market-leading", "best-in-class" and "seamless" are struck at review.</li>
            <li><strong>Client vocabulary wins.</strong> If the client calls it a "customer hub", we call it a customer hub, not a portal.</li>
            <li><strong>Validity is 30 days</strong> from issue unless the pre-sales lead has approved a longer window in writing.</li>
            <li><strong>One owner per next step.</strong> A next step without a named person is not a next step.</li>
        </ul>

        <h2>3. Tone</h2>
        <p>Direct, specific and confident without overclaiming. Write to a sceptical technical buyer who has
        read three other proposals this week. Prefer short sentences and concrete nouns. State risks plainly —
        a proposal that names a risk and its mitigation reads as more credible than one that omits it.</p>

        <h2>4. Required front and back matter</h2>
        <table>
            <thead><tr><th>Element</th><th>Placement</th><th>Source</th></tr></thead>
            <tbody>
                <tr><td>Client name, industry and proposal reference</td><td>Cover</td><td>CRM deal record</td></tr>
                <tr class="alt"><td>Prepared by / date / validity</td><td>Cover</td><td>Pre-sales lead</td></tr>
                <tr><td>Security and compliance annex</td><td>Appendix A</td><td>SEC-002, attached unmodified</td></tr>
                <tr class="alt"><td>Standard terms reference</td><td>Appendix B</td><td>Legal, current version only</td></tr>
            </tbody>
        </table>
        <p class="small">Deviations from this template require written approval from the Pre-Sales Lead before issue.</p>
        HTML;
    }

    /**
     * Carries the 12% discount threshold quoted verbatim in the commercial pricing task, and the
     * on-premise / regulated-data uplifts the estimate consolidation is expected to apply.
     */
    private static function rateCard(): string
    {
        return <<<'HTML'
        <p class="lede">Standard day rates effective 1 January 2026, the uplifts that apply to constrained
        delivery models, and the discount authority matrix. All figures in EUR, excluding VAT and expenses.</p>

        <div class="callout callout-warn">
            <p><strong>Confidential.</strong> This document is not to be shared with clients or included as a
            proposal appendix. Quote the derived price, never the rate card itself.</p>
        </div>

        <h2>1. Day rates by role</h2>
        <table>
            <thead><tr><th>Role</th><th>Band</th><th class="num">Standard</th><th class="num">Regulated</th><th class="num">Floor</th></tr></thead>
            <tbody>
                <tr><td>Principal consultant</td><td>P4</td><td class="num">1,450</td><td class="num">1,650</td><td class="num">1,240</td></tr>
                <tr class="alt"><td>Solution architect</td><td>P3</td><td class="num">1,250</td><td class="num">1,420</td><td class="num">1,060</td></tr>
                <tr><td>Technical lead</td><td>E4</td><td class="num">1,080</td><td class="num">1,220</td><td class="num">920</td></tr>
                <tr class="alt"><td>Senior engineer</td><td>E3</td><td class="num">920</td><td class="num">1,040</td><td class="num">780</td></tr>
                <tr><td>Engineer</td><td>E2</td><td class="num">740</td><td class="num">830</td><td class="num">630</td></tr>
                <tr class="alt"><td>Junior engineer</td><td>E1</td><td class="num">540</td><td class="num">610</td><td class="num">460</td></tr>
                <tr><td>Cloud / infrastructure engineer</td><td>I3</td><td class="num">960</td><td class="num">1,090</td><td class="num">820</td></tr>
                <tr class="alt"><td>Security consultant</td><td>S3</td><td class="num">1,180</td><td class="num">1,340</td><td class="num">1,000</td></tr>
                <tr><td>Delivery lead / project manager</td><td>D3</td><td class="num">980</td><td class="num">1,110</td><td class="num">830</td></tr>
                <tr class="alt"><td>Business analyst</td><td>B2</td><td class="num">780</td><td class="num">880</td><td class="num">660</td></tr>
                <tr><td>QA engineer</td><td>Q2</td><td class="num">700</td><td class="num">790</td><td class="num">595</td></tr>
            </tbody>
        </table>
        <p class="small">"Regulated" applies where the engagement processes PII, PCI or PHI data, or requires
        cleared personnel. "Floor" is the lowest rate that may be quoted with Managing Director approval.</p>

        <h2>2. Blended rates</h2>
        <table>
            <thead><tr><th>Engagement shape</th><th>Typical mix</th><th class="num">Blended day rate</th></tr></thead>
            <tbody>
                <tr><td>Portal / web build</td><td>1 &times; E4, 2 &times; E3, 1 &times; E2, 0.5 &times; Q2</td><td class="num">890</td></tr>
                <tr class="alt"><td>Integration programme</td><td>1 &times; P3, 1 &times; E4, 2 &times; E3, 0.5 &times; B2</td><td class="num">1,010</td></tr>
                <tr><td>Data platform</td><td>1 &times; P3, 2 &times; E3, 1 &times; I3</td><td class="num">1,020</td></tr>
                <tr class="alt"><td>On-premise deployment</td><td>1 &times; P3, 1 &times; I3, 1 &times; S3, 1 &times; E3</td><td class="num">1,160</td></tr>
            </tbody>
        </table>

        <h2>3. Delivery model uplifts</h2>
        <p>Uplifts are cumulative and apply to the blended rate before any discount is calculated.</p>
        <table>
            <thead><tr><th>Condition</th><th class="num">Uplift</th><th>Rationale</th></tr></thead>
            <tbody>
                <tr><td>On-premise deployment</td><td class="num">+18%</td><td>Environment access, change windows, on-site presence.</td></tr>
                <tr class="alt"><td>Regulated data (PII / PCI / PHI)</td><td class="num">+12%</td><td>Control evidence, audit support, restricted tooling.</td></tr>
                <tr><td>Air-gapped or sovereign hosting</td><td class="num">+25%</td><td>Manual release path, cleared personnel.</td></tr>
                <tr class="alt"><td>Client-mandated tooling</td><td class="num">+8%</td><td>Ramp-up outside the standard toolchain.</td></tr>
                <tr><td>Fixed price (scope-locked)</td><td class="num">+15%</td><td>Risk transfer to Company.</td></tr>
            </tbody>
        </table>

        <h2>4. Discount authority</h2>
        <div class="callout callout-warn">
            <p><strong>Any discount above 12% requires manager sign-off before the proposal is issued.</strong>
            This is the threshold enforced at the commercial pricing step — a proposal that reaches manager
            approval carrying an unapproved discount is returned for rework.</p>
        </div>
        <table>
            <thead><tr><th class="num">Discount</th><th>Approval required</th><th>Evidence needed</th></tr></thead>
            <tbody>
                <tr><td class="num">0 &ndash; 12%</td><td>Commercial Analyst</td><td>None; record the reason on the deal.</td></tr>
                <tr class="alt"><td class="num">12.01 &ndash; 20%</td><td>Pre-Sales Lead</td><td>Competitive position and expected follow-on revenue.</td></tr>
                <tr><td class="num">20.01 &ndash; 30%</td><td>Managing Director</td><td>Written business case; margin impact modelled.</td></tr>
                <tr class="alt"><td class="num">Above 30%</td><td>Managing Director + Board note</td><td>Strategic rationale; never on a first engagement.</td></tr>
            </tbody>
        </table>

        <h2>5. Payment terms</h2>
        <table>
            <thead><tr><th>Option</th><th>Structure</th><th>Applies to</th></tr></thead>
            <tbody>
                <tr><td>30 days</td><td>Monthly arrears, net 30 from invoice date.</td><td>Time-and-materials engagements.</td></tr>
                <tr class="alt"><td>50/50 milestone</td><td>50% on signature, 50% on acceptance.</td><td>Fixed-price engagements under 150 person-days.</td></tr>
                <tr><td>Monthly retainer</td><td>Fixed monthly fee against a committed capacity.</td><td>Hypercare and managed service periods.</td></tr>
            </tbody>
        </table>
        <p class="small">Rates are reviewed annually. Late payment interest accrues per the standard terms.</p>
        HTML;
    }

    /**
     * Defines the phase/person-days/rate-band table the child workflow's `ai-generator` is asked to emit.
     */
    private static function phaseModel(): string
    {
        return <<<'HTML'
        <p class="lede">The seven-phase delivery model every Company estimate is broken down against. Using a
        consistent phase split is what makes two estimates comparable, and what lets the SOW library be used
        as a benchmark at all.</p>

        <h2>1. Phase split</h2>
        <p>Percentages are of total delivery effort and are the starting point, not the answer. Adjust them
        against the specifics of the engagement and state any material deviation in the assumptions.</p>
        <table>
            <thead><tr>
                <th class="num">#</th><th>Phase</th><th class="num">Typical effort</th>
                <th>Dominant rate band</th><th>Primary deliverable</th>
            </tr></thead>
            <tbody>
                <tr><td class="num">1</td><td><strong>Discovery</strong></td><td class="num">10%</td><td>P3 / B2</td><td>Requirements baseline, integration inventory</td></tr>
                <tr class="alt"><td class="num">2</td><td><strong>Solution design</strong></td><td class="num">15%</td><td>P3 / E4</td><td>Solution architecture, interface contracts</td></tr>
                <tr><td class="num">3</td><td><strong>Build</strong></td><td class="num">40%</td><td>E3 / E2</td><td>Working software, unit and component tests</td></tr>
                <tr class="alt"><td class="num">4</td><td><strong>Integration</strong></td><td class="num">15%</td><td>E4 / E3</td><td>End-to-end integrated environment</td></tr>
                <tr><td class="num">5</td><td><strong>UAT</strong></td><td class="num">10%</td><td>Q2 / B2</td><td>Signed-off acceptance test results</td></tr>
                <tr class="alt"><td class="num">6</td><td><strong>Cutover</strong></td><td class="num">5%</td><td>I3 / D3</td><td>Production release, rollback plan executed or retired</td></tr>
                <tr><td class="num">7</td><td><strong>Hypercare</strong></td><td class="num">5%</td><td>E3 / D3</td><td>Stabilised service, handover to run</td></tr>
            </tbody>
        </table>

        <h2>2. Required estimate output format</h2>
        <p>Every effort breakdown must be produced as a table with exactly these columns, one row per phase,
        plus a total row. Do not merge phases and do not omit a phase — a phase estimated at zero must still
        appear, with a note explaining why.</p>
        <div class="callout">
            <p><code>| Phase | Person-days | Rate band |</code></p>
            <p class="small">Person-days rounded to the nearest whole day. Rate band is the dominant band from
            section 1 unless the engagement shape justifies otherwise.</p>
        </div>

        <h2>3. Exit criteria</h2>
        <table>
            <thead><tr><th>Phase</th><th>Cannot exit until&hellip;</th></tr></thead>
            <tbody>
                <tr><td>Discovery</td><td>The integration inventory is complete and every external dependency has a named owner on the client side.</td></tr>
                <tr class="alt"><td>Solution design</td><td>Interface contracts are agreed in writing and the non-functional requirements are quantified.</td></tr>
                <tr><td>Build</td><td>All acceptance criteria have automated coverage and the component test suite passes on the shared branch.</td></tr>
                <tr class="alt"><td>Integration</td><td>An end-to-end transaction completes in an environment representative of production.</td></tr>
                <tr><td>UAT</td><td>Client sign-off is recorded, with any open defect triaged and accepted or scheduled.</td></tr>
                <tr class="alt"><td>Cutover</td><td>Production is serving live traffic and the rollback window has closed.</td></tr>
                <tr><td>Hypercare</td><td>Defect arrival rate is below the agreed threshold for two consecutive weeks and run handover is signed.</td></tr>
            </tbody>
        </table>

        <h2>4. Phase adjustments by engagement shape</h2>
        <table>
            <thead><tr><th>Shape</th><th>Adjustment</th><th>Reason</th></tr></thead>
            <tbody>
                <tr><td>On-premise deployment</td><td>Cutover 5% &rarr; 12%, Hypercare 5% &rarr; 8%</td><td>Change windows, environment parity, on-site release.</td></tr>
                <tr class="alt"><td>Regulated data</td><td>Discovery 10% &rarr; 14%, add control evidence to every phase</td><td>DPIA, control mapping and audit evidence.</td></tr>
                <tr><td>Heavy integration (4+ systems)</td><td>Integration 15% &rarr; 22%, Build 40% &rarr; 35%</td><td>Coordination and third-party test windows dominate.</td></tr>
                <tr class="alt"><td>Greenfield, single system</td><td>Discovery 10% &rarr; 7%, Build 40% &rarr; 45%</td><td>Little legacy discovery required.</td></tr>
            </tbody>
        </table>
        <p class="small">Owned by Delivery. Any new phase shape must be added here before it is used in a client estimate.</p>
        HTML;
    }

    /**
     * Cited by the expert-review summary and attached to proposals as Appendix A. The 24-hour security
     * review SLA below matches the `dueWithin` on the runtime sub-flow's security review task.
     */
    private static function securityWhitepaper(): string
    {
        return <<<'HTML'
        <p class="lede">How Company secures client data across cloud, hybrid and on-premise deliveries, which
        certifications we hold, and what a client is responsible for in an on-premise deployment. This document
        is client-shareable and is attached unmodified as Appendix A to every proposal.</p>

        <h2>1. Certifications and attestations</h2>
        <table>
            <thead><tr><th>Standard</th><th>Scope</th><th>Certificate</th><th>Issued</th><th>Expires</th></tr></thead>
            <tbody>
                <tr><td>ISO/IEC 27001:2022</td><td>Software delivery and managed services</td><td>IS-742119</td><td>14 Mar 2025</td><td>13 Mar 2028</td></tr>
                <tr class="alt"><td>ISO/IEC 27701:2019</td><td>Privacy information management</td><td>PI-742119-P</td><td>14 Mar 2025</td><td>13 Mar 2028</td></tr>
                <tr><td>SOC 2 Type II</td><td>Security, Availability, Confidentiality</td><td>SOC2-2025-0448</td><td>30 Sep 2025</td><td>29 Sep 2026</td></tr>
                <tr class="alt"><td>Cyber Essentials Plus</td><td>Corporate IT estate</td><td>CE-P-118204</td><td>02 Feb 2026</td><td>01 Feb 2027</td></tr>
            </tbody>
        </table>
        <p class="small">Certificates and the current SOC 2 report are available under NDA on request.</p>

        <h2>2. Data residency</h2>
        <table>
            <thead><tr><th>Option</th><th>Regions</th><th>Data at rest</th><th>Support access</th></tr></thead>
            <tbody>
                <tr><td>EU standard</td><td>Frankfurt, Dublin</td><td>AES-256, customer-managed keys available</td><td>EU-based personnel only</td></tr>
                <tr class="alt"><td>UK</td><td>London</td><td>AES-256, customer-managed keys available</td><td>UK-based personnel only</td></tr>
                <tr><td>Client premises</td><td>Client data centre</td><td>Client-managed</td><td>Named engineers, client-approved</td></tr>
            </tbody>
        </table>

        <h2>3. On-premise deployments</h2>
        <p>An on-premise deployment moves a substantial share of control — and therefore responsibility — to
        the client. The split below is the default and is confirmed per engagement during Discovery.</p>
        <table>
            <thead><tr><th>Control</th><th>Company</th><th>Client</th></tr></thead>
            <tbody>
                <tr><td>Application security (SAST, dependency scanning, secure SDLC)</td><td>Owns</td><td>Reviews</td></tr>
                <tr class="alt"><td>Infrastructure hardening and patching</td><td>Advises</td><td>Owns</td></tr>
                <tr><td>Network segmentation and firewalling</td><td>Advises</td><td>Owns</td></tr>
                <tr class="alt"><td>Identity, access provisioning and privileged access</td><td>Advises</td><td>Owns</td></tr>
                <tr><td>Backup, restore and disaster recovery testing</td><td>Advises</td><td>Owns</td></tr>
                <tr class="alt"><td>Encryption key custody</td><td>No access</td><td>Owns</td></tr>
                <tr><td>Log retention and SIEM integration</td><td>Advises</td><td>Owns</td></tr>
                <tr class="alt"><td>Penetration testing before go-live</td><td>Coordinates</td><td>Commissions</td></tr>
            </tbody>
        </table>
        <div class="callout callout-warn">
            <p><strong>Assumption that must be stated in every on-premise proposal.</strong> Company cannot be
            accountable for controls it does not operate. Where a client expects Company to carry infrastructure
            or key-custody responsibility, that is a managed-service engagement and is priced separately.</p>
        </div>

        <h2>4. Regulated data</h2>
        <p>Where an engagement processes personal, cardholder or health data, the following are mandatory and
        are costed into the estimate rather than absorbed:</p>
        <ul>
            <li>A Data Protection Impact Assessment completed before the Build phase begins.</li>
            <li>A signed Data Processing Agreement with a documented sub-processor list.</li>
            <li>Data minimisation review — production data is never used in non-production environments.</li>
            <li>Pseudonymisation or synthetic data for all test environments.</li>
            <li>Control evidence captured per phase and retained for the contractual audit window.</li>
            <li>Breach notification to the client within 24 hours of confirmation.</li>
        </ul>

        <h2>5. Security review service levels</h2>
        <table>
            <thead><tr><th>Activity</th><th>Trigger</th><th class="num">Target</th></tr></thead>
            <tbody>
                <tr><td>Pre-sales security review</td><td>Opportunity classified complex, or on-premise / regulated</td><td class="num">24 hours</td></tr>
                <tr class="alt"><td>Infrastructure sizing review</td><td>On-premise or hybrid deployment</td><td class="num">8 hours</td></tr>
                <tr><td>Contract and data terms review</td><td>Regulated data, or non-standard terms requested</td><td class="num">24 hours</td></tr>
                <tr class="alt"><td>Vulnerability triage (critical)</td><td>Disclosure or scan finding</td><td class="num">4 hours</td></tr>
                <tr><td>Confirmed breach notification</td><td>Incident confirmed</td><td class="num">24 hours</td></tr>
            </tbody>
        </table>

        <h2>6. Sub-processors</h2>
        <table>
            <thead><tr><th>Sub-processor</th><th>Purpose</th><th>Region</th></tr></thead>
            <tbody>
                <tr><td>Northwind Cloud Services</td><td>Primary hosting and compute</td><td>EU (Frankfurt, Dublin)</td></tr>
                <tr class="alt"><td>Ardent Observability</td><td>Application logging and metrics</td><td>EU (Frankfurt)</td></tr>
                <tr><td>Marlow Identity</td><td>Workforce identity and SSO</td><td>EU (Amsterdam)</td></tr>
                <tr class="alt"><td>Kirkwall Backup</td><td>Encrypted offsite backup</td><td>EU (Stockholm)</td></tr>
            </tbody>
        </table>
        <p class="small">Clients are notified at least 30 days before a sub-processor is added or replaced.
        Security contact: security@company.example.</p>
        HTML;
    }
}
