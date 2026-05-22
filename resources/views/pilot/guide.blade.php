<x-app-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Operator Guide</h1>
            <p class="mt-1 text-sm text-slate-600">Short guide for a local pilot operator.</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <ul class="space-y-3 text-sm text-slate-700">
                <li><strong>Simulator:</strong> creates a local incoming message and request for testing.</li>
                <li><strong>Incoming messages:</strong> every received text is stored here first.</li>
                <li><strong>Requests:</strong> review items created from messages.</li>
                <li><strong>Pending:</strong> looks clear enough to review manually.</li>
                <li><strong>Needs review:</strong> message is unclear or mixed and needs manual review.</li>
                <li><strong>Confirm:</strong> accept a request after checking it.</li>
                <li><strong>Reject:</strong> deny an invalid request and add a reason.</li>
                <li><strong>Close the day:</strong> snapshot branch totals at the end of the day.</li>
                <li><strong>Export CSV:</strong> download the closure request list for records.</li>
            </ul>
        </div>
    </div>
</x-app-layout>
