# Yoast Redirect GraphQL Checker

A lightweight WordPress plugin that adds a custom GraphQL field to [WPGraphQL](https://www.wpgraphql.com/), allowing you to check if a given URL has a redirect configured in **Yoast SEO Premium**.

This is ideal for **headless WordPress** or **decoupled frontends** (e.g., Next.js, React, Gatsby) that need access to redirect logic stored in Yoast.

---

## ✨ Features

- 🔎 Check if a specific URL has a redirect configured in Yoast
- 🧠 Supports both **plain** and **regex** redirect formats
- ⚡ Efficient — does not return the full redirect list
- 🔁 Returns `origin`, `target`, `type`, and `format`
- 🧩 Designed for GraphQL-based frontends
- ✅ No database query — uses Yoast's internal redirect store

---

## 🧠 Use Case

You’re using Yoast SEO Premium to manage redirects in WordPress, and your frontend is built in React, Next.js, or another decoupled stack. This plugin allows your frontend to:

- ✅ Fetch redirect data at build/runtime
- ✅ Avoid relying on server-side redirect logic
- ✅ Match both exact and regex-based redirect rules

---

## 🛠 Installation

1. Clone or download this repo into your WordPress `plugins` folder:
wp-content/plugins/yoast-graphql-redirect-checker/


2. Activate the plugin from the **WordPress admin dashboard**.

---

## 🚀 GraphQL Usage

### 🔍 Query a redirect for a specific URL

```graphql
query {
  yoastRedirectForUrl(url: "/old-page") {
    origin
    target
    type
    format
  }
}
```

## 🔁 Example Response
```json
{
  "data": {
    "yoastRedirectForUrl": {
      "origin": "/old-page",
      "target": "/new-page",
      "type": "301",
      "format": "plain"
    }
  }
}
```

---

## ▲ Next.js Integration

The typical setup is a `proxy.ts` (Next.js 15+; `middleware.ts` on older versions) that checks each request against WordPress *before* the page renders, and redirects or lets it through. Query only `target` and `type` — this field is meant to be a cheap existence check, not a way to fetch the full redirect list.

```graphql
# graphql/redirect.ts
query RedirectionQuery($url: String!) {
  yoastRedirectForUrl(url: $url) {
    target
    type
  }
}
```

Call WPGraphQL through your own internal API route rather than directly from `proxy.ts`/`middleware.ts`. Middleware runs in a separate runtime that can't share modules, connections, or caches with the rest of your app ([Next.js docs](https://nextjs.org/docs/app/api-reference/file-conventions/middleware)) — calling WPGraphQL straight from there means a second, independent client with its own auth/session handling. Routing through an internal route instead reuses the one GraphQL client your pages already use, including its caching:

```ts
// app/api/check-redirect/route.ts
import { NextResponse } from "next/server";
import { executeQuery } from "../../../lib/graphql-client"; // your existing client
import { RedirectionQuery } from "../../../graphql/redirect";

export async function GET(request: Request) {
  const url = new URL(request.url).searchParams.get("path");
  if (!url) return NextResponse.json({ redirect: null }, { status: 400 });

  const result = await executeQuery(RedirectionQuery, { url });
  const redirection = result?.data?.yoastRedirectForUrl;

  if (!redirection?.target) {
    return NextResponse.json({ redirect: null });
  }

  return NextResponse.json({
    redirect: {
      target: redirection.target,
      permanent: Number(redirection.type) === 301,
    },
  });
}
```

```ts
// proxy.ts
import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

async function checkRedirect(request: NextRequest, pathname: string) {
  const url = new URL("/api/check-redirect", request.url);
  url.searchParams.set("path", pathname);

  try {
    const res = await fetch(url);
    if (!res.ok) return null;
    return (await res.json()).redirect;
  } catch {
    // Fail open: a check that can't complete should never block a request.
    return null;
  }
}

export async function proxy(request: NextRequest) {
  const redirect = await checkRedirect(request, request.nextUrl.pathname);

  if (redirect) {
    return NextResponse.redirect(
      new URL(redirect.target, request.url),
      redirect.permanent ? 308 : 307,
    );
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!api|_next/static|_next/image|favicon.ico).*)"],
};
```

Two things worth keeping in any real implementation:
- **Fail open.** If WordPress or WPGraphQL is briefly unreachable, the check should never block a request — treat a failed/errored lookup the same as "no redirect", not as a hard failure.
- **Cache the lookup** (e.g. Next.js's `"use cache"` + `cacheTag`/`cacheLife`, keyed by the normalized path) so a redirect check isn't a fresh WPGraphQL round trip on every request for the same URL.

## ⚙️ Requirements
- WordPress 5.5+
- PHP 7.4+
- Yoast SEO Premium (redirects are a Premium-only feature)
- WPGraphQL

If either dependency is missing or inactive, the plugin shows an admin notice instead of failing silently — `yoastRedirectForUrl` will simply always resolve to `null` until both are active.

## 🧑‍💻 Developer Notes
- The plugin uses `WPSEO_Redirect_Option()->get_from_option()` to read the current list of configured redirects — no direct database query.
- Regex rules are evaluated using `preg_match()`; the pattern comes from Yoast's stored config (trusted), the subject is the requested URL (untrusted). A malformed saved pattern is treated as "no match" rather than breaking the whole lookup.
- Matching is based only on the URL path (domain and query string are ignored).
- The `url` argument is required (`String!`) — omitting it is a GraphQL validation error, not a silent `null`.

## 🔒 Security Considerations
`yoastRedirectForUrl` is registered on the public `RootQuery` type with no capability check, by design — a redirect-existence check needs to work for anonymous frontend visitors. This means **any GraphQL client can query any URL's redirect status**, including origin/target pairs for redirects you didn't expect to expose publicly. If your WPGraphQL setup already restricts introspection or requires authentication for all queries, this field follows those same restrictions; it does not add any additional exposure beyond whatever your WPGraphQL access model already allows for public queries.

## 📄 License
[MIT License](https://mit-license.org/)

Copyright (c) 2025 Finanzero

## 🤝 Contributing
Pull requests and suggestions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines and [CHANGELOG.md](CHANGELOG.md) for version history.

## 🙋‍♂️ Author & Maintainers
Designed and built by [Leonardo Assef](https://github.com/assef) — conceived, architected, and implemented solo to solve redirect handling for [Finanzero](https://github.com/finanzero)'s headless Next.js frontend. Owned and maintained by Finanzero.

## 🔗 Plugin Links
- [Yoast SEO Premium](yoast.com)
- [WPGraphQL](wpgraphql.com)