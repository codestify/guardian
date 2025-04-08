@if(config('guardian.client.auto_include_script', true))
    <script>
        // Guardian configuration
        window.guardianConfig = {
            endpoint: "{{ url('/__guardian__/report') }}", // Always use absolute URL for consistency
            sampleRate: {{ config('guardian.client.sample_rate', 0.5) }},
            threshold: {{ config('guardian.detection.threshold', 60) }},
            debug: {{ config('app.debug', false) ? 'true' : 'false' }},
        };
    </script>
    <script src="{{ asset('vendor/guardian/js/guardian.min.js') }}" defer></script>
@endif
