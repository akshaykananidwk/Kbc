| Module | Test | Result | Status |
|---|---|---|---|
| 1. Installation | storage/installed.lock exists | as expected | PASS |
| 1. Installation | /install is locked after installation | HTTP 403 | PASS |
| 1. Installation | all required tables exist | 30 tables | PASS |
| 1. Installation | InnoDB engine | InnoDB | PASS |
| 1. Installation | utf8mb4 collation | utf8mb4_unicode_ci | PASS |
| 2. Authentication | unauthenticated /admin redirects to login | HTTP 302 | PASS |
| 2. Authentication | login form carries a CSRF token | 64 chars | PASS |
| 2. Authentication | wrong password is rejected | Invalid credentials. | PASS |
| 2. Authentication | correct password signs in | /admin | PASS |
| 2. Authentication | /admin reachable once signed in | 200 | PASS |
| 2. Authentication | API reports the signed-in user | csrf token issued | PASS |
| 3. Security | POST without a CSRF token is refused | 16 | PASS |
| 3. Security | POST with a valid CSRF token succeeds | 17 | PASS |
| 3. Security | SQL injection payloads are inert | 30 tables intact | PASS |
| 3. Security | no time-based SQL injection | 4 queries in 0.02s | PASS |
| 3. Security | stored XSS is escaped on output | payload rendered as text | PASS |
| 3. Security | header x-content-type-options | nosniff | PASS |
| 3. Security | header x-frame-options | SAMEORIGIN | PASS |
| 3. Security | Content-Security-Policy is set | default-src 'self'; script-src... | PASS |
| 3. Security | admin pages are marked noindex | noindex, nofollow, noarchive, nosnippet | PASS |
| 3. Security | direct access blocked: /app/Core/Database.php | HTTP 403 | PASS |
| 3. Security | direct access blocked: /.env | HTTP 403 | PASS |
| 3. Security | direct access blocked: /storage/logs | HTTP 403 | PASS |
| 3. Security | direct access blocked: /config/app.php | HTTP 403 | PASS |
| 3. Security | direct access blocked: /database/migrations | HTTP 403 | PASS |
| 4. Authorisation | operator can open the operator screen | 200 | PASS |
| 4. Authorisation | operator blocked from /admin/questions | HTTP 302 | PASS |
| 4. Authorisation | operator blocked from /admin/settings | HTTP 302 | PASS |
| 4. Authorisation | operator blocked from /admin/users | HTTP 302 | PASS |
| 4. Authorisation | operator blocked from /admin/backups | HTTP 302 | PASS |
| 4. Authorisation | operator blocked from /admin/updates | HTTP 302 | PASS |
| 4. Authorisation | operator blocked from the settings API | You are not allowed to perform this action. | PASS |
| 5. CRUD modules | Dashboard renders | HTTP 200, 9551b | PASS |
| 5. CRUD modules | Questions renders | HTTP 200, 49916b | PASS |
| 5. CRUD modules | Categories renders | HTTP 200, 39270b | PASS |
| 5. CRUD modules | Participants renders | HTTP 200, 14629b | PASS |
| 5. CRUD modules | Prize ladder renders | HTTP 200, 46286b | PASS |
| 5. CRUD modules | Gifts renders | HTTP 200, 12142b | PASS |
| 5. CRUD modules | Lifelines renders | HTTP 200, 19434b | PASS |
| 5. CRUD modules | Game history renders | HTTP 200, 7502b | PASS |
| 5. CRUD modules | Reports renders | HTTP 200, 9635b | PASS |
| 5. CRUD modules | Settings renders | HTTP 200, 102371b | PASS |
| 5. CRUD modules | Users renders | HTTP 200, 7078b | PASS |
| 5. CRUD modules | Backups renders | HTTP 200, 9193b | PASS |
| 5. CRUD modules | Updates renders | HTTP 200, 14942b | PASS |
| 5. CRUD modules | Audit log renders | HTTP 200, 26318b | PASS |
| 5. CRUD modules | Operator console renders | HTTP 200, 11783b | PASS |
| 5. CRUD modules | Operator setup renders | HTTP 200, 15730b | PASS |
| 5. CRUD modules | Display screen renders | HTTP 200, 11762b | PASS |
| 5. CRUD modules | Home page renders | HTTP 200, 2223b | PASS |
| 5. CRUD modules | question created | id 821 | PASS |
| 5. CRUD modules | question options stored | 4 | PASS |
| 5. CRUD modules | Gujarati text stored unchanged | એક | PASS |
| 5. CRUD modules | question updated | D | PASS |
| 5. CRUD modules | question duplicated | copy id 822 | PASS |
| 5. CRUD modules | unused question deleted | as expected | PASS |
| 5. CRUD modules | participant created | id 84 | PASS |
| 5. CRUD modules | registration number auto-assigned | as expected | PASS |
| 5. CRUD modules | prize level created | level 12 = 640000 | PASS |
| 5. CRUD modules | guaranteed flag stored | 1 | PASS |
| 5. CRUD modules | prize level deleted | as expected | PASS |
| 5. CRUD modules | gift created | id 49 | PASS |
| 5. CRUD modules | gift deleted | as expected | PASS |
| 5. CRUD modules | CSV export export | HTTP 200, UTF-8 BOM present | PASS |
| 5. CRUD modules | CSV export games.csv | HTTP 200, UTF-8 BOM present | PASS |
| 5. CRUD modules | CSV export questions.csv | HTTP 200, UTF-8 BOM present | PASS |
| 5. CRUD modules | CSV export prizes.csv | HTTP 200, UTF-8 BOM present | PASS |
| 6. Game engine | game created | Game created. | PASS |
| 6. Game engine | initial state | PARTICIPANT_INTRO | PASS |
| 6. Game engine | game starts at level 1 | 1 | PASS |
| 6. Game engine | state after start | QUESTION_DISPLAYED | PASS |
| 6. Game engine | a question is on air | ગણેશજીની બે પત્નીઓનાં નામ શું છે? | PASS |
| 6. Game engine | timer is loaded but not running | 30000ms | PASS |
| 6. Game engine | timer starts | running | PASS |
| 6. Game engine | timer counts down in real time | 29386ms left of 30000ms after 600ms | PASS |
| 6. Game engine | timer pauses | TIMER_PAUSED | PASS |
| 6. Game engine | paused timer does not drift | held at 29372ms | PASS |
| 6. Game engine | timer resumes | running | PASS |
| 6. Game engine | timer resets to full | 30000 | PASS |
| 6. Game engine | 50:50 removes two options | 2 | PASS |
| 6. Game engine | 50:50 keeps the correct answer | B,C kept | PASS |
| 6. Game engine | a lifeline cannot be used twice | The 50:50 lifeline has already been used. | PASS |
| 6. Game engine | audience poll announces itself by default | announce | PASS |
| 6. Game engine | and invents no percentages | the hall answers | PASS |
| 6. Game engine | a simulated poll totals 100% | 100 | PASS |
| 6. Game engine | poll gives 0% to options 50:50 removed | as expected | PASS |
| 6. Game engine | expert suggests a surviving option | suggested B | PASS |
| 7. Display screen security (the critical requirement) | correct answer is null before the reveal | present and null | PASS |
| 7. Display screen security (the critical requirement) | explanation is null before the reveal | present and null | PASS |
| 7. Display screen security (the critical requirement) | no "private" section in the display payload | as expected | PASS |
| 7. Display screen security (the critical requirement) | the string "correct_option" appears nowhere | as expected | PASS |
| 7. Display screen security (the critical requirement) | no password or token data anywhere | as expected | PASS |
| 7. Display screen security (the critical requirement) | options removed by 50:50 are null, not hidden client-side | A = null | PASS |
| 7. Display screen security (the critical requirement) | the operator DOES receive the correct answer at the same moment | operator sees B | PASS |
| 8. Answer flow | an option removed by 50:50 cannot be selected | Option A was removed by the 50:50 lifeline. | PASS |
| 8. Answer flow | option selected | C | PASS |
| 8. Answer flow | selection can be changed before locking | B | PASS |
| 8. Answer flow | reveal is refused before the answer is locked | Lock the answer before revealing the result. | PASS |
| 8. Answer flow | answer locks | locked | PASS |
| 8. Answer flow | locking stops the timer | timer stopped | PASS |
| 8. Answer flow | a locked answer cannot be changed | The answer is locked. Use the override control to change it. | PASS |
| 8. Answer flow | correct answer STILL hidden after locking | still null | PASS |
| 8. Answer flow | display shows the answer as locked | as expected | PASS |
| 8. Answer flow | operator override unlocks the answer | unlocked | PASS |
| 8. Answer flow | the override is written to the audit log | 44 | PASS |
| 8. Answer flow | correct answer gives state CORRECT | CORRECT | PASS |
| 8. Answer flow | prize awarded for level 1 | 500 | PASS |
| 8. Answer flow | correct answer IS released after the reveal | B | PASS |
| 8. Answer flow | explanation released after the reveal | as expected | PASS |
| 9. Two screen synchronisation | state_version advances on every operator action | 16 -> 17 | PASS |
| 9. Two screen synchronisation | operator state endpoint responded | Current game state. | PASS |
| 9. Two screen synchronisation | both screens agree on the level | 2 | PASS |
| 9. Two screen synchronisation | both screens agree on the question | અષ્ટવિનાયકનાં આઠ મંદિરો કયા રાજ્યમાં આવેલાં છે? | PASS |
| 9. Two screen synchronisation | both screens agree on the state | QUESTION_DISPLAYED | PASS |
| 9. Two screen synchronisation | long-poll returns promptly when nothing changes | 2.7s | PASS |
| 10. Prize, guarantee and gift logic | reached the guaranteed level | 5 | PASS |
| 10. Prize, guarantee and gift logic | guarantee is only banked once the level is answered | 0 before answering level 5 | PASS |
| 10. Prize, guarantee and gift logic | guaranteed amount is banked after answering it | 10000 | PASS |
| 10. Prize, guarantee and gift logic | a gift was awarded on a gift level | 2 gift(s) recorded | PASS |
| 10. Prize, guarantee and gift logic | gift stock decremented | quantity_used = 1 | PASS |
| 10. Prize, guarantee and gift logic | advanced past the guaranteed level | now at level 6 | PASS |
| 10. Prize, guarantee and gift logic | wrong answer gives state WRONG | WRONG | PASS |
| 10. Prize, guarantee and gift logic | game ends on a wrong answer | wrong_answer | PASS |
| 10. Prize, guarantee and gift logic | final prize falls back to the guaranteed amount | 10000 | PASS |
| 10. Prize, guarantee and gift logic | display shows the final amount | ₹10,000 | PASS |
| 11. Transaction safety | a failure during reveal throws | as expected | PASS |
| 11. Transaction safety | the game row is unchanged after rollback | state stayed ANSWER_LOCKED | PASS |
| 11. Transaction safety | no orphan answer row | as expected | PASS |
| 11. Transaction safety | gift stock unchanged | 2 | PASS |
| 11. Transaction safety | the game is still playable | ANSWER_LOCKED | PASS |
| 11. Transaction safety | reveal succeeds once the fault is removed | CORRECT | PASS |
| 12. Game reset and completion | reset clears the level | as expected | PASS |
| 12. Game reset and completion | reset clears recorded answers | as expected | PASS |
| 12. Game reset and completion | reset clears used lifelines | as expected | PASS |
| 12. Game reset and completion | reset preserves the audit log | audit rows kept and added | PASS |
| 12. Game reset and completion | reset returns gift stock | 2 | PASS |
| 12. Game reset and completion | unused questions are tracked across games | 194 unused of 200 active | PASS |
| 12. Game reset and completion | clearing the ladder completes the game | completed | PASS |
| 12. Game reset and completion | final prize is the top prize | 320000 | PASS |
| 12. Game reset and completion | questions answered | 10 | PASS |
| 12. Game reset and completion | question-reuse setting restored | false | PASS |
| 12. Game reset and completion | timer expires without any client action | TIME_UP | PASS |
| 12. Game reset and completion | time up is recorded as a timeout | timeout | PASS |
| 12. Game reset and completion | time up ends the game | time_up | PASS |
| 13. Backup and restore | database backup created | backup_2026-09-20_00-44-41_db.zip (117.4 KB) | PASS |
| 13. Backup and restore | backup contains a manifest | as expected | PASS |
| 13. Backup and restore | dump contains every table | 30 CREATE TABLE statements | PASS |
| 13. Backup and restore | data destroyed before the restore | as expected | PASS |
| 13. Backup and restore | restore brings the rows back | 200 | PASS |
| 13. Backup and restore | a safety backup was taken before restoring | backup_2026-09-20_00-44-41-1_db.zip | PASS |
| 13. Backup and restore | Gujarati text survives backup and restore byte for byte | ગણેશજીની બે પત્નીઓનાં નામ શું છે? | PASS |
| 13. Backup and restore | files backup created | backup_2026-09-20_00-44-41_files.zip (472.05 KB, 230 files) | PASS |
| 13. Backup and restore | files backup contains the application | as expected | PASS |
| 13. Backup and restore | files backup excludes existing backups | as expected | PASS |
| 14. Migrations, cache and the updater | no migrations are pending | [] | PASS |
| 14. Migrations, cache and the updater | migration history is recorded | 16 applied | PASS |
| 14. Migrations, cache and the updater | cache stores a value | as expected | PASS |
| 14. Migrations, cache and the updater | cache clear removes the value | as expected | PASS |
| 14. Migrations, cache and the updater | cache clear leaves uploads alone | 1 | PASS |
| 14. Migrations, cache and the updater | cache clear leaves the database alone | as expected | PASS |
| 14. Migrations, cache and the updater | protected path rule: .env | true | PASS |
| 14. Migrations, cache and the updater | protected path rule: storage/logs/app.log | true | PASS |
| 14. Migrations, cache and the updater | protected path rule: public/uploads/questions/a.jpg | true | PASS |
| 14. Migrations, cache and the updater | protected path rule: config/local.php | true | PASS |
| 14. Migrations, cache and the updater | protected path rule: app/Core/Database.php | false | PASS |
| 14. Migrations, cache and the updater | protected path rule: index.php | false | PASS |
| 14. Migrations, cache and the updater | update check runs without a repository configured | not configured (expected on a fresh install) | PASS |
| 14. Migrations, cache and the updater | cache clear API works | Cache cleared. 0 entr(ies) removed. Uploads and the database were not touched. | PASS |
| 15. Audit log | logged: login | 192 entries | PASS |
| 15. Audit log | logged: game.created | 722 entries | PASS |
| 15. Audit log | logged: game.answer_locked | 2710 entries | PASS |
| 15. Audit log | logged: game.result_revealed | 2707 entries | PASS |
| 15. Audit log | logged: game.reset | 56 entries | PASS |
| 15. Audit log | logged: question.created | 89 entries | PASS |
| 15. Audit log | audit entries record an IP address | 1941 entries with an IP | PASS |
| 15. Audit log | no secrets written to the audit log | as expected | PASS |
| 16. Responsive and accessibility markup | Admin declares a viewport | as expected | PASS |
| 16. Responsive and accessibility markup | Operator declares a viewport | as expected | PASS |
| 16. Responsive and accessibility markup | Display declares a viewport | as expected | PASS |
| 16. Responsive and accessibility markup | admin CSS has mobile breakpoints | 5 media queries | PASS |
| 16. Responsive and accessibility markup | admin CSS sets 16px inputs on mobile (prevents iOS zoom) | as expected | PASS |
| 16. Responsive and accessibility markup | display CSS scales with the viewport | 217 sizes follow the screen unit | PASS |
| 16. Responsive and accessibility markup | display CSS respects reduced motion | as expected | PASS |
| 17. Media settings (the inline upload fix) | sound settings render an upload control, not a text box | inline media widget present | PASS |
| 17. Media settings (the inline upload fix) | music settings exist | intro and background music fields | PASS |
| 17. Media settings (the inline upload fix) | no free-text path box for a sound setting | old text input is gone | PASS |
| 17. Media settings (the inline upload fix) | music uploads over AJAX | File uploaded. | PASS |
| 17. Media settings (the inline upload fix) | the uploaded music is stored in its setting | uploads/branding/20260920-28194b405f8a3d3cae5106ad.mp3 | PASS |
| 17. Media settings (the inline upload fix) | the file really exists on disk | 20260920-28194b405f8a3d3cae5106ad.mp3 | PASS |
| 17. Media settings (the inline upload fix) | the display API serves the music URL | /public/uploads/branding/20260920-28194b405f8a3d3cae5106ad.mp3 | PASS |
| 17. Media settings (the inline upload fix) | a PHP file renamed to .mp3 is refused | The file content does not match its extension. | PASS |
| 17. Media settings (the inline upload fix) | music can be removed again | as expected | PASS |
| 17. Media settings (the inline upload fix) | the setting is cleared | as expected | PASS |
| 18. Sound that works with no files | the audio engine ships | 9055 bytes | PASS |
| 18. Sound that works with no files | synthesised tone defined: question_start | as expected | PASS |
| 18. Sound that works with no files | synthesised tone defined: correct_answer | as expected | PASS |
| 18. Sound that works with no files | synthesised tone defined: wrong_answer | as expected | PASS |
| 18. Sound that works with no files | synthesised tone defined: final_win | as expected | PASS |
| 18. Sound that works with no files | synthesised tone defined: game_over | as expected | PASS |
| 18. Sound that works with no files | synthesised tone defined: tick | as expected | PASS |
| 18. Sound that works with no files | tones are generated, not sampled | Web Audio oscillators, no bundled audio files | PASS |
| 18. Sound that works with no files | the display loads the audio engine | as expected | PASS |
| 18. Sound that works with no files | the settings page loads the audio engine too | as expected | PASS |
| 18. Sound that works with no files | every sound setting has a working Test sound button | 13 test buttons | PASS |
| 18. Sound that works with no files | the test button knows which built-in tone to play | as expected | PASS |
| 18. Sound that works with no files | the tone player can be opened from a click | unlockNow wired to the button | PASS |
| 19. Question audio and video reach the display | the display renders question video | as expected | PASS |
| 19. Question audio and video reach the display | the display plays question audio | as expected | PASS |
| 19. Question audio and video reach the display | question audio is in the display payload | /public/uploads/questions/verify-tone.mp3 | PASS |
| 20. Winner certificates | a certificate is issued for a finished game | GQC-2026-0001 | PASS |
| 20. Winner certificates | it carries the final prize | 320000 | PASS |
| 20. Winner certificates | a reprint keeps the same serial number | GQC-2026-0001 | PASS |
| 20. Winner certificates | the print count increases | print 1 | PASS |
| 20. Winner certificates | the certificate page renders | 200 | PASS |
| 20. Winner certificates | it shows the winner and the amount | serial and amount present | PASS |
| 20. Winner certificates | it is laid out for A4 landscape printing | as expected | PASS |
| 20. Winner certificates | the certificates list renders | 200 | PASS |
| 21. Hall of fame and sponsors | the leaderboard lists the winner | 1 entries | PASS |
| 21. Hall of fame and sponsors | entries carry a prize label | ₹3,20,000 | PASS |
| 21. Hall of fame and sponsors | the summary counts real games | 1 games | PASS |
| 21. Hall of fame and sponsors | the idle screen is active after a finished game | as expected | PASS |
| 21. Hall of fame and sponsors | sponsors reach the display | 2 sponsors | PASS |
| 21. Hall of fame and sponsors | the leaderboard reaches the display | as expected | PASS |
| 21. Hall of fame and sponsors | the sponsors screen renders | 200 | PASS |
| 22. QR codes | a QR code is generated as SVG | 6764 bytes | PASS |
| 22. QR codes | the matrix is square and sized to a real version | version 3, 29x29 | PASS |
| 22. QR codes | longer URLs pick a larger version | as expected | PASS |
| 22. QR codes | QR endpoint serves register | HTTP 200 | PASS |
| 22. QR codes | QR endpoint serves display | HTTP 200 | PASS |
| 22. QR codes | an unknown QR target is refused | 404 | PASS |
| 23. Live audience voting | voting opens with a short code | QGPLJN | PASS |
| 23. Live audience voting | the code reaches the display | as expected | PASS |
| 23. Live audience voting | the voting page opens on a phone | 200 | PASS |
| 23. Live audience voting | a code of the wrong length is not routed at all | 404 | PASS |
| 23. Live audience voting | the voting page never contains the answer | no answer in the markup | PASS |
| 23. Live audience voting | every phone is counted once | 15 | PASS |
| 23. Live audience voting | a phone changing its mind does not add a vote | 15 | PASS |
| 23. Live audience voting | percentages total 100 | 100 | PASS |
| 23. Live audience voting | the results are marked as live | live | PASS |
| 23. Live audience voting | the lifeline uses the real votes | live | PASS |
| 23. Live audience voting | with no votes the poll falls back to simulated | mode announce | PASS |
| 24. Public self-registration | the registration page opens | 200 | PASS |
| 24. Public self-registration | a person can register themselves | GQ-006 | PASS |
| 24. Public self-registration | registering twice does not duplicate | same registration returned | PASS |
| 24. Public self-registration | only one row exists | 1 | PASS |
| 24. Public self-registration | the honeypot absorbs bots silently | 6 | PASS |
| 25. Fastest Finger First | a round is created | pending | PASS |
| 25. Fastest Finger First | every contender is entered | 3 | PASS |
| 25. Fastest Finger First | the answer is withheld while pending | as expected | PASS |
| 25. Fastest Finger First | each contender gets an access code | 3 | PASS |
| 25. Fastest Finger First | codes are four characters | as expected | PASS |
| 25. Fastest Finger First | the public view has no private section | as expected | PASS |
| 25. Fastest Finger First | the answer stays hidden while running | as expected | PASS |
| 25. Fastest Finger First | no answer is marked right or wrong while running | as expected | PASS |
| 25. Fastest Finger First | the answer is released once closed | BDAC | PASS |
| 25. Fastest Finger First | the fastest correct answer wins | Rajesh Patel | PASS |
| 25. Fastest Finger First | a fast but wrong answer never ranks | wrong answer excluded from the ranking | PASS |
| 25. Fastest Finger First | an invalid access code is refused | That code is not valid for this round. | PASS |
| 25. Fastest Finger First | the contender page opens | 200 | PASS |
| 25. Fastest Finger First | the contender page never contains the answer | as expected | PASS |
| 25. Fastest Finger First | the Fastest Finger operator screen renders | 200 | PASS |
| 26. Gujarati and Hindi interface | translations are available | en, gu, hi | PASS |
| 26. Gujarati and Hindi interface | Gujarati translates a control | રમત શરૂ કરો | PASS |
| 26. Gujarati and Hindi interface | Hindi translates a control | खेल शुरू करें | PASS |
| 26. Gujarati and Hindi interface | English falls through to the key | Start the game | PASS |
| 26. Gujarati and Hindi interface | an unknown key degrades to readable English | Some Untranslated Label | PASS |
| 26. Gujarati and Hindi interface | the operator screen renders in Gujarati | controls translated | PASS |
| 26. Gujarati and Hindi interface | the admin navigation renders in Gujarati | as expected | PASS |
| 27. Rehearsal mode | a game can be created as a rehearsal | as expected | PASS |
| 27. Rehearsal mode | a rehearsal does not move gift stock | as expected | PASS |
| 27. Rehearsal mode | a rehearsal does not skew question statistics | as expected | PASS |
| 27. Rehearsal mode | a rehearsal is hidden from game history | as expected | PASS |
| 27. Rehearsal mode | it can still be found on request | as expected | PASS |
| 27. Rehearsal mode | a rehearsal is excluded from the statistics | as expected | PASS |
| 27. Rehearsal mode | a rehearsal is excluded from the leaderboard | [] | PASS |
| 27. Rehearsal mode | a rehearsal gets no certificate | Rehearsal games do not get certificates. | PASS |
| 28. Screen fit and real music uploads | a hidden panel leaves the layout (display) | as expected | PASS |
| 28. Screen fit and real music uploads | a hidden panel leaves the layout (operator) | as expected | PASS |
| 28. Screen fit and real music uploads | a hidden panel leaves the layout (admin) | as expected | PASS |
| 28. Screen fit and real music uploads | every size on the display scales from one unit | 217 scaled values | PASS |
| 28. Screen fit and real music uploads | the prize ladder scales on its own | so a long ladder never shrinks the question | PASS |
| 28. Screen fit and real music uploads | the display measures itself and fits the screen | as expected | PASS |
| 28. Screen fit and real music uploads | the timer keeps its own space | never squeezed off the screen | PASS |
| 28. Screen fit and real music uploads | the stage is a fixed grid that cannot overflow | as expected | PASS |
| 28. Screen fit and real music uploads | server limit reads upload_max_filesize | 2097152 | PASS |
| 28. Screen fit and real music uploads | server limit reads kilobytes | 524288 | PASS |
| 28. Screen fit and real music uploads | server limit reads gigabytes | 1073741824 | PASS |
| 28. Screen fit and real music uploads | a plain byte count is understood | 4096 | PASS |
| 28. Screen fit and real music uploads | the app knows what this server accepts | 2 MB | PASS |
| 28. Screen fit and real music uploads | no upload field promises more than the server allows | all capped at 2 MB | PASS |
| 28. Screen fit and real music uploads | the settings page states the real limit | as expected | PASS |
| 28. Screen fit and real music uploads | the upload control knows the limit before sending | as expected | PASS |
| 28. Screen fit and real music uploads | a real 1.8 MB song uploads | File uploaded. | PASS |
| 28. Screen fit and real music uploads | the song is stored and served | uploads/branding/20260920-935b71b0ff2886657d51662e.mp3 | PASS |
| 28. Screen fit and real music uploads | a stock 1 MB server advertises its real limit | admin is told before trying | PASS |
| 28. Screen fit and real music uploads | an oversized upload is refused, not silently lost | 413 | PASS |
| 28. Screen fit and real music uploads | and the message says exactly what to change | That file is too big for this server, so nothing was received. Your server currently accepts uploads up to 1 MB. To allow larger music files, raise upload_max_filesize and post_max_size: Settings -> Sound has a button that writes a .user.ini for you, and shows the text to upload by hand if your host will not let PHP write it. | PASS |
| 29. Starting a game never dead-ends | a game is created | GQ260920-A987 | PASS |
| 29. Starting a game never dead-ends | a second game is refused while one is open | Game GQ260920-A987 is still open for Rajesh Patel. End it and start the new game? | PASS |
| 29. Starting a game never dead-ends | the refusal names the game that is blocking | GQ260920-A987 | PASS |
| 29. Starting a game never dead-ends | and who was playing it | Rajesh Patel | PASS |
| 29. Starting a game never dead-ends | the operator can take over in one step | GQ260920-DFA8 | PASS |
| 29. Starting a game never dead-ends | the abandoned game is closed, not deleted | abandoned | PASS |
| 29. Starting a game never dead-ends | the takeover is written to the audit log | as expected | PASS |
| 29. Starting a game never dead-ends | every question is now used | as expected | PASS |
| 29. Starting a game never dead-ends | without reuse an exhausted bank is reported clearly | No unused question is available for level 1. Add more questions, or allow questions to be reused for this game. | PASS |
| 29. Starting a game never dead-ends | allowing reuse for one game gets the show on air | સુદર્શન ચક્ર કયા ભગવાનનું શસ્ત્ર છે? | PASS |
| 29. Starting a game never dead-ends | the global setting is untouched | false | PASS |
| 29. Starting a game never dead-ends | the setup screen renders | 200 | PASS |
| 29. Starting a game never dead-ends | it offers per-game question reuse | as expected | PASS |
| 29. Starting a game never dead-ends | the create button is not dead when a game is open | button stays usable | PASS |
| 30. Updates survive a locked-down host | the update completes despite a locked .htaccess | 3 file(s) written | PASS |
| 30. Updates survive a locked-down host | the locked file is reported, not silently skipped | .htaccess | PASS |
| 30. Updates survive a locked-down host | the host's own server config is left exactly as it was | untouched | PASS |
| 30. Updates survive a locked-down host | the application files are updated all the same | index.php and new classes written | PASS |
| 30. Updates survive a locked-down host | a genuinely unwritable application file still fails loudly | Could not write file: index.php. Check that the folder "application root" is writable by PHP. | PASS |
| 30. Updates survive a locked-down host | and the message says which folder to check | as expected | PASS |
| 30. Updates survive a locked-down host | the limits template asks for a usable size | as expected | PASS |
| 30. Updates survive a locked-down host | the helper reports the live limit | 2 MB | PASS |
| 30. Updates survive a locked-down host | and knows whether that is too small for a song | true | PASS |
| 30. Updates survive a locked-down host | the admin panel can create .user.ini where the host allows it | .user.ini created, asking for 64M uploads. PHP caches this file, so allow up to five minutes, then reload this page to see the new limit. If it does not change, your host applies its own limit and you will need to raise it in the hosting control panel. | PASS |
| 30. Updates survive a locked-down host | the file it writes is the documented one | as expected | PASS |
| 30. Updates survive a locked-down host | .user.ini is never shipped in the repository itself | it is created on the server, never updated over | PASS |
| 30. Updates survive a locked-down host | the template ships for manual installation | as expected | PASS |
| 31. An update actually reaches the screens | the display stylesheet address carries a version | assets/css/display.css?v=1789845248 | PASS |
| 31. An update actually reaches the screens | so does the display script | as expected | PASS |
| 31. An update actually reaches the screens | and every admin asset | as expected | PASS |
| 31. An update actually reaches the screens | and every operator asset | as expected | PASS |
| 31. An update actually reaches the screens | the version is the file's own timestamp | 1789845248 | PASS |
| 31. An update actually reaches the screens | changing the file changes the address browsers ask for | 1789845248 -> 1789845291 | PASS |
| 31. An update actually reaches the screens | a missing asset still produces a usable URL | tests/public/assets/css/not-here.css | PASS |
| 31. An update actually reaches the screens | the VERSION file ships with the package | 1.7.0 | PASS |
| 31. An update actually reaches the screens | the reported version is the one on disk | 1.7.0 | PASS |
| 31. An update actually reaches the screens | a stale setting from an earlier update cannot mislead | 1.7.0 | PASS |
| 31. An update actually reaches the screens | the display shows the running version to the operator | v1.7.0 | PASS |
| 32. Right answer shown, whole bank used | measurement ignores the highlight animations | as expected | PASS |
| 32. Right answer shown, whole bank used | a highlighted option settles back to its own size | it pops, then returns - so it is never clipped by the prize ladder | PASS |
| 32. Right answer shown, whole bank used | the size is only recalculated when the layout really changes | no twitch mid-question | PASS |
| 32. Right answer shown, whole bank used | full-screen panels size themselves | so a result panel never shrinks the board | PASS |
| 32. Right answer shown, whole bank used | the board marks the wrong answer and the right one | as expected | PASS |
| 32. Right answer shown, whole bank used | red and green are actually used | as expected | PASS |
| 32. Right answer shown, whole bank used | the result panel has a row for each answer | as expected | PASS |
| 32. Right answer shown, whole bank used | it names the correct answer in words, not just a letter | option text is shown | PASS |
| 32. Right answer shown, whole bank used | the panel waits so the coloured board can be seen first | panel follows the board | PASS |
| 32. Right answer shown, whole bank used | the explanation is shown once the result is revealed | as expected | PASS |
| 32. Right answer shown, whole bank used | the answer is still hidden while it is only locked | present and null | PASS |
| 32. Right answer shown, whole bank used | after the reveal the display knows the right answer | B | PASS |
| 32. Right answer shown, whole bank used | and which one was given | A | PASS |
| 32. Right answer shown, whole bank used | both answers have their text on the screen | the panel can name them | PASS |
| 33. Question rotation | rotation serves every question before repeating any | 12 | PASS |
| 33. Question rotation | so nothing is served twice in the first full round | 1 | PASS |
| 33. Question rotation | the next round starts over rather than stopping | 4 question(s) served | PASS |
| 33. Question rotation | without rotation the same questions come back while others wait | 9 of 12 used, worst repeat 2 | PASS |
| 33. Question rotation | serving a question is recorded | 1 | PASS |
| 33. Question rotation | with the time it was served | 2026-09-20 00:44:48 | PASS |
| 33. Question rotation | the bank reports its size | 12 | PASS |
| 33. Question rotation | and how many have never been used | 11 | PASS |
| 33. Question rotation | the operator sees the state of the bank before a show | as expected | PASS |
| 34. Gujarati question bank | the question bank ships with the app | as expected | PASS |
| 34. Gujarati question bank | it holds the full 200 questions | 200 | PASS |
| 34. Gujarati question bank | every question is complete and unique | as expected | PASS |
| 34. Gujarati question bank | it covers five categories | 5 | PASS |
| 34. Gujarati question bank | with the same number in each | ધાર્મિકતા=40, દેશભક્તિ=40, ભારતદર્શન=40, કરંટ અફેર્સ=40, જનરલ નોલેજ=40 | PASS |
| 34. Gujarati question bank | the questions are in Gujarati | ભગવાન ગણેશનું વાહન કયું છે? | PASS |
| 34. Gujarati question bank | loaded into the database: ધાર્મિકતા | 40 active question(s) | PASS |
| 34. Gujarati question bank | loaded into the database: દેશભક્તિ | 40 active question(s) | PASS |
| 34. Gujarati question bank | loaded into the database: ભારતદર્શન | 40 active question(s) | PASS |
| 34. Gujarati question bank | loaded into the database: કરંટ અફેર્સ | 40 active question(s) | PASS |
| 34. Gujarati question bank | loaded into the database: જનરલ નોલેજ | 40 active question(s) | PASS |
| 34. Gujarati question bank | the five categories are the ones a balanced show rotates through | 5 in rotation | PASS |
| 34. Gujarati question bank | a Gujarati name gives a stable slug | item-1aae2affd8 | PASS |
| 34. Gujarati question bank | and different names give different slugs | item-1aae2affd8 | PASS |
| 34. Gujarati question bank | re-seeding does not duplicate a category | as expected | PASS |
| 35. Two questions from every category | game 1: every category is used | 5 | PASS |
| 35. Two questions from every category | game 1: 2 question(s) from each | દેશભક્તિ=2, કરંટ અફે=2, ભારતદર્શ=2, જનરલ નોલ=2, ધાર્મિકત=2 | PASS |
| 35. Two questions from every category | game 2: every category is used | 5 | PASS |
| 35. Two questions from every category | game 2: 2 question(s) from each | કરંટ અફે=2, ધાર્મિકત=2, દેશભક્તિ=2, ભારતદર્શ=2, જનરલ નોલ=2 | PASS |
| 35. Two questions from every category | and the running order differs from show to show | દેશભ→કરંટ→ભારત→જનરલ→ધાર્→દેશભ→કરંટ→ભારત→ | PASS |
| 35. Two questions from every category | a level keeps its category if the game is restarted | ભારતદર્શન | PASS |
| 35. Two questions from every category | the operator can choose the balanced order | as expected | PASS |
| 36. Lifelines the room answers itself | the audience poll only announces itself | announce | PASS |
| 36. Lifelines the room answers itself | it invents no percentages | the hall answers, the screen does not guess | PASS |
| 36. Lifelines the room answers itself | and it shows how long the audience has | 30 seconds | PASS |
| 36. Lifelines the room answers itself | Phone a Friend is available | as expected | PASS |
| 36. Lifelines the room answers itself | it is an announcement too | announce | PASS |
| 36. Lifelines the room answers itself | with a countdown for the call | 30 seconds | PASS |
| 36. Lifelines the room answers itself | neither lifeline leaks the answer to the display | still hidden | PASS |
| 36. Lifelines the room answers itself | the display has a panel for an announced lifeline | as expected | PASS |
| 36. Lifelines the room answers itself | it counts down on screen | as expected | PASS |
| 37. Choosing which categories take part | the categories screen renders | 200 | PASS |
| 37. Choosing which categories take part | it shows which categories are in rotation | as expected | PASS |
| 37. Choosing which categories take part | unticking the box takes a category out of the rotation | as expected | PASS |
| 37. Choosing which categories take part | and ticking it puts the category back | 1 | PASS |
| 37. Choosing which categories take part | renaming a Gujarati category keeps its questions | 40 question(s) still attached | PASS |
| 38. Show day setup | the shipped setup turns on exactly those three | set by the show-day migration | PASS |
| 38. Show day setup | three lifelines are offered | 3 | PASS |
| 38. Show day setup | and they are the three the show promises | fifty_fifty · phone_a_friend · skip_question | PASS |
| 38. Show day setup | the question swap is named in Gujarati | પ્રશ્ન બદલી | PASS |
| 38. Show day setup | a 14 year old is a junior | junior | PASS |
| 38. Show day setup | a 35 year old is a senior | senior | PASS |
| 38. Show day setup | and the group can be pinned by hand | senior | PASS |
| 38. Show day setup | a junior is only asked questions open to them | picked #565 | PASS |
| 38. Show day setup | a senior can be asked the senior-only ones | picked #669 | PASS |
| 38. Show day setup | a junior can still play when every question is senior | the picker falls back rather than stalling the show | PASS |
| 38. Show day setup | and the fallback is written to the game log | recorded, so it is never silent | PASS |
| 38. Show day setup | the counter still reports what a junior can be asked | true | PASS |
| 38. Show day setup | three shows in a row repeat nothing | 30 | PASS |
| 38. Show day setup | and the counter shows what is left for each group | 169 junior · 169 senior | PASS |
| 38. Show day setup | turning the switch off frees the whole bank again | 200 available | PASS |
| 38. Show day setup | the swap serves a different question | #640 → #641 | PASS |
| 38. Show day setup | the prize level does not change | 1 | PASS |
| 38. Show day setup | and the prize is the same | 500 | PASS |
| 38. Show day setup | the swapped question is remembered | 1 | PASS |
| 38. Show day setup | so it cannot come back later in the same game | swapped away for good | PASS |
| 38. Show day setup | the display is told to announce the swap | announce | PASS |
| 38. Show day setup | the operator has a single switch for the day | as expected | PASS |
| 38. Show day setup | it reports what is left for juniors and seniors | as expected | PASS |
| 38. Show day setup | the button turns the rule off | false | PASS |
| 38. Show day setup | and back on | true | PASS |
| 38. Show day setup | the participants list has an entry-gift button | as expected | PASS |
| 38. Show day setup | one click records the gift | 2026-09-20 00:44:51 | PASS |
| 38. Show day setup | and clicking again undoes it | as expected | PASS |
| 39. Replacing the question bank | a second, senior bank ships | as expected | PASS |
| 39. Replacing the question bank | it holds 200 questions | 200 | PASS |
| 39. Replacing the question bank | every senior question is complete and unique | as expected | PASS |
| 39. Replacing the question bank | it covers the same five categories | 5 | PASS |
| 39. Replacing the question bank | with forty in each | ધાર્મિકત=40, દેશભક્તિ=40, ભારતદર્શ=40, કરંટ અફે=40, જનરલ નોલ=40 | PASS |
| 39. Replacing the question bank | not one question is shared with the open bank | as expected | PASS |
| 39. Replacing the question bank | and it is pitched harder | 191 of 200 are medium or hard | PASS |
| 39. Replacing the question bank | clearing removes every question | as expected | PASS |
| 39. Replacing the question bank | and every option with it | as expected | PASS |
| 39. Replacing the question bank | it reports what it removed | 200 | PASS |
| 39. Replacing the question bank | participants, prizes and settings are untouched | only questions and the games that used them go | PASS |
| 39. Replacing the question bank | the senior bank loads | 200 | PASS |
| 39. Replacing the question bank | every loaded question is marked senior | 200 | PASS |
| 39. Replacing the question bank | each category holds forty | 5 | PASS |
| 39. Replacing the question bank | and each question kept its four options | 800 options | PASS |
| 39. Replacing the question bank | the replace screen renders | 200 | PASS |
| 39. Replacing the question bank | it warns what will be removed | as expected | PASS |
| 39. Replacing the question bank | and offers both banks | as expected | PASS |
| 39. Replacing the question bank | the wrong confirmation word changes nothing | 200 | PASS |
| 39. Replacing the question bank | the right word replaces the bank | 200 | PASS |
| 39. Replacing the question bank | and a backup is taken first | recoverable from Admin → Backups | PASS |
| 39. Replacing the question bank | the replacement is written to the audit log | as expected | PASS |
