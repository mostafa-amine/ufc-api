# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_AUTH_KEY}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Get a free API key by sending a <code>POST /v1/register</code> with your name, email and password. Then send it as <code>Authorization: Bearer {YOUR_API_KEY}</code>.
