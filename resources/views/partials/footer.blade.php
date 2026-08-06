    <!-- Footer one start-->
    <footer class="brator-footer-area gray-bg design-two design-three">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-12">
                    <div class="brator-copyright-area">
                        {{--
                            Was "© 2022 Copyrights by [Brator Inc.] All Rights Reserved." — the
                            company name was a dead link, and the year was frozen at 2022, so a
                            live shop stated the wrong year in its own footer.

                            The year now comes from the clock. A hardcoded one is wrong every
                            January, and nobody thinks about the footer in January.
                        --}}
                        <p>© {{ now()->year }} {{ config('app.name') }}. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <!-- Footer one end-->
