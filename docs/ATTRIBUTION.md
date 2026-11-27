# Attribution and upstream notices

Fork Panel XC is distributed under **AGPL-3.0**. It is a fork of
[XC_VM](https://github.com/Vateron-Media/XC_VM) (Vateron Media), which in turn
builds on the open Xtream UI ecosystem. The original copyright, license and
footer attribution are preserved.

## XC_VM / flpanel2

Stream monitoring and stream-management behavior in this fork was reviewed
against the open-source XC_VM codebase (AGPL-3.0), including the mirror at
[`TalaveraSama/flpanel2`](https://github.com/TalaveraSama/flpanel2):

- per-stream monitoring lifecycle and failure state,
- failure thresholds before marking a source offline,
- automatic restart when a monitored source becomes unavailable,
- global "check all streams" operations,
- stream audit/error logging.

Those ideas were **reimplemented for this fork's API + gateway architecture**
(PDO migrations, `StreamMonitor`, `StreamProxy`, Vue tabs). No proprietary
XUI.one code was used. Where code is adapted from XC_VM in the future, the
AGPL-3.0 notices and attribution must be kept.

## Third-party components

- Vue 3, Vite, Pinia, Vue Router, Tailwind CSS, Chart.js, Heroicons.
- PHP 8.3, MariaDB, nginx, Redis.
- TMDB metadata via The Movie Database API.

See `LICENSE` for the full AGPL-3.0 text.
