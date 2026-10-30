# KageGate Video Presentation Script

**Suggested runtime:** 8–9 minutes, followed by questions if required  
**Project:** KageGate — an anime and donghua discovery website  
**Course context:** CS ELEC 5 — Parallel and Distributed Computing  
**Presenters:** [Presenter name(s)]  
**Date:** [Presentation date]

Replace the bracketed details before recording. Use a disposable demo account and never show real passwords, database credentials, authenticator setup keys, QR codes, or personal account information on camera.

## Presentation script

### 1. Opening and project purpose — 0:00–0:40

**On screen:** Title slide with the KageGate name, project title, presenters, and course.

**Say:**

“Good day. We are [presenter name or names], and this is our presentation of KageGate, an anime and donghua discovery website built with PHP and MySQL.

KageGate helps signed-in users discover titles, filter and search the catalog, view title details, follow upcoming episodes, and maintain a personal watchlist. We will demonstrate the working features, explain the system design and its distributed-service interactions, and be clear about one important limitation: the current application does not implement parallel processing.”

### 2. What the application does — 0:40–1:30

**On screen:** Show the sign-in page briefly, then the Discover page. Point out the schedule, catalog filters, and title cards.

**Say:**

“KageGate is a web application rather than a video-hosting service. Its catalog metadata comes from AniList. The project includes anime, donghua, manga, manhwa, and novels, along with search and filters such as genre, season, studio, status, and format.

The Discover page also shows eligible upcoming episode times for the next seven days in Philippine time. Users can open a title’s detail page, add it to a watchlist, and manage account settings. Episode links lead to the source listed by AniList; KageGate does not host or proxy episode videos.”

### 3. System architecture and implementation — 1:30–2:40

**On screen:** Show an architecture slide:

`Browser → PHP application → MySQL`

Add a separate connection from the PHP application to `AniList GraphQL API`, and label the browser–PHP connection “HTTP”.

**Say:**

“The browser sends page requests to the PHP application running under a web server such as Apache. The application handles authentication, page rendering, search and filtering, and database operations. MySQL stores account records, watchlists, notifications, passkey public credentials, and cached catalog data.

For catalog data, the PHP application communicates with AniList’s GraphQL API over HTTPS. It caches the catalog in MySQL for six hours and caches the airing schedule for ten minutes. This reduces repeated external requests while keeping the application’s displayed data available from its cache.

The implementation uses prepared PDO statements for database queries, PHP sessions for signed-in state, and shared configuration and helper functions. The database schema defines relationships between users and their watchlists, passkeys, and notifications. These are the main components and data flows we will show in the demo.”

### 4. Parallel and distributed computing concepts — 2:40–3:35

**On screen:** Return to the architecture diagram. Highlight the browser, PHP server, MySQL database, and AniList as separate communicating components.

**Say:**

“For the course concept, it is important to distinguish distributed communication from parallel computation. KageGate has a distributed, client-server structure: a browser, a PHP web application, a MySQL database, and the external AniList service communicate across process or network boundaries. The application sends HTTPS requests to AniList, and the database is a separate persistence service.

However, the current project does not split a job across workers, run parallel API requests, or implement a synchronization mechanism for parallel tasks. We are not claiming that it does. The catalog query and the application’s request handling should be understood as ordinary web-service communication, not as a demonstrated parallel-computing feature.

If extended, independent catalog requests could be evaluated for concurrent execution, with API rate limits, failure handling, and cache consistency measured and tested. That would be future work, not part of this demonstration.”

### 5. Live application demonstration — 3:35–5:55

**On screen:** Switch to the local KageGate site running through XAMPP/Apache. Use a prepared demo account.

**Say and do:**

1. **Show signed-in access:** “I’m signing in with a disposable demo account. The catalog is for signed-in users, so a logged-out visitor is directed to the login page.”
2. **Demonstrate discovery:** “Here is the Discover page. I can browse the catalog and narrow it using the category, search, genre, season, studio, status, and format controls.”
3. **Demonstrate details:** Open one title. “The detail page uses the catalog metadata and provides the available title information and source links.”
4. **Demonstrate watchlist:** Add the title to the watchlist, then show the watchlist state. “The watchlist is associated with the signed-in user and persists in MySQL.”
5. **Show schedule:** Return to Discover and point to a schedule entry if one is available. “This schedule is based on future airing information returned by AniList; it will not show an entry when the source has no future episode time.”
6. **Optional account-security view:** Show only the account security page’s enabled or disabled status. “The project also provides authenticator MFA and passkey settings. I will not reveal a setup QR code or secret during this recording.”

**Presenter note:** If the local API or database is unavailable, do not fake a successful result. Show the relevant screen and explain the dependency or use a clearly labeled recording of a previously completed demo.

### 6. Technical knowledge and security — 5:55–7:10

**On screen:** Show a simple sign-in/MFA flow diagram:

`Password or passkey → optional TOTP verification → signed-in session`

**Say:**

“KageGate supports password sign-in, optional Google sign-in when configured, passkeys, and authenticator-based MFA. When MFA is enabled, a successful first sign-in step is not enough to create the authenticated session: the user must also pass the TOTP verification step.

The authenticator setup follows RFC 6238 with a six-digit code and a 30-second period. The project generates the enrollment QR code in the browser rather than sending the secret to an external QR service. Passkeys use WebAuthn public-key credentials: the application stores the credential’s public key, not a fingerprint or face image. Device verification is performed by the browser and authenticator.

The implementation also uses CSRF tokens for state-changing requests, password hashing and verification, session regeneration at login, and prepared database statements. These measures improve the application’s protections, but they do not make this educational project a production-ready identity service. The README documents additional production work that would be needed, including stronger account-based throttling, recovery options, and independent security review.”

### 7. Documentation, sources, and limitations — 7:10–8:00

**On screen:** Show the project’s References page and briefly show the project README or database schema.

**Say:**

“Our references are listed in the project’s References page. AniList is used for catalog metadata; RFC 6238 informs the time-based one-time-password implementation; W3C WebAuthn informs passkey behavior; and Google’s OpenID Connect documentation informs the optional Google sign-in flow. The project also identifies LuciferDonghua as a title-discovery reference only, not as a source of hosted or proxied video.

The README documents setup requirements, database import, feature behavior, hosting caveats, and security limitations. We have also identified the absence of parallel execution as a project limitation rather than presenting it as an implemented feature.”

### 8. Closing — 8:00–8:20

**On screen:** Return to the title slide.

**Say:**

“To conclude, KageGate demonstrates a PHP and MySQL discovery application that integrates an external metadata API, persistent user features, and optional modern authentication. Its distributed component is the communication among the browser, application server, database, and AniList. Parallel processing remains future work. Thank you for listening; we are ready for your questions.”

## Likely questions and suggested answers

**Q: Where is the parallel processing in this project?**  
**A:** “There is no implemented parallel-processing workflow in the current version. The project demonstrates client-server and API communication across separate components, which is distributed interaction, but we do not claim that it parallelizes tasks. A future version could test concurrent retrieval of independent data, while measuring API rate limits, latency, failures, and cache consistency.”

**Q: How does the catalog avoid calling AniList on every page request?**  
**A:** “The application stores the catalog response in MySQL and uses a six-hour cache. The airing schedule has a separate ten-minute cache. If the external service is unavailable, the application’s behavior depends on whether usable cached data is available.”

**Q: What happens when MFA is enabled?**  
**A:** “After the first sign-in step, the account remains pending until the user enters a valid time-based code. Only then does the application finish creating the signed-in session.”

**Q: Does KageGate stream the episodes?**  
**A:** “No. It displays metadata and links to sources listed by AniList. KageGate does not host or proxy the video, and availability depends on the external source and region.”

**Q: Is the site ready for production?**  
**A:** “It is an educational project tested locally with XAMPP. The README explicitly says hosting has not been verified and lists additional production security and operational work. We would not describe it as production-ready without that work and an independent review.”

## Rubric alignment checklist

| Rubric criterion | Evidence in this presentation |
|---|---|
| Project functionality and completeness — 20 points | Sections 2 and 5 demonstrate real discovery, filtering, title details, watchlist, schedule, and account access. |
| Parallel and distributed computing concepts — 20 points | Section 4 accurately identifies distributed communication and explicitly discloses that parallel execution is not implemented. |
| System architecture and implementation — 20 points | Section 3 explains the browser, PHP application, MySQL database, AniList API, and caching. |
| Presentation and demonstration — 20 points | The timed sections, architecture visual, prepared live demo, and closing are organized for an 8–9 minute recording. |
| Technical knowledge and question-and-answer — 10 points | Section 6 explains key security flows; the Q&A provides concise, technically grounded answers. |
| Documentation and academic integrity — 10 points | Section 7 identifies sources and distinguishes project functionality from future work. |

## Sources to show or cite

- KageGate project README and `references.php`
- AniList — <https://anilist.co/>
- RFC 6238, *TOTP: Time-Based One-Time Password Algorithm* — <https://datatracker.ietf.org/doc/html/rfc6238>
- W3C Web Authentication — <https://www.w3.org/TR/webauthn-2/>
- Google OpenID Connect — <https://developers.google.com/identity/openid-connect/openid-connect>
- LuciferDonghua — <https://luciferdonghua.in/> (title-discovery reference only)
