---
title: Deliverability & compliance
---

# Deliverability & compliance

- Configure **SPF**, **DKIM**, and **DMARC** for every domain you send from.
- Warm up new inboxes gradually and keep daily volume modest; providers throttle and flag bursts.
- A campaign can be restricted to certain weekdays and to an hour range. A step that lands outside
  that window moves to the next allowed slot, worked out in the recipient's timezone. Leaving both
  hours at 00:00 sends at any hour.
- A connection's `sending_limit` is a per-day allowance. It counts sends since midnight UTC and
  resets when the UTC day rolls over, so reaching the limit delays a campaign rather than stopping it.
- Every campaign email carries an unsubscribe link and one-click `List-Unsubscribe` headers.
  Unsubscribed addresses are suppressed for the team.
- Open and link tracking can be turned off per campaign. The unsubscribe link is added either way,
  so a campaign with tracking off still carries it.
- You, the operator, are responsible for complying with laws such as CAN-SPAM and GDPR: having
  a lawful basis for contacting people, including a physical postal address in your email
  footer, and honoring unsubscribe requests.
- Open and click tracking stores events together with the user agent. How long you retain that
  data, and how you handle deletion requests, is your responsibility.

This section is general information, not legal advice.
