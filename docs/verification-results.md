| Module | Test | Result | Status |
|---|---|---|---|
| 1. Installation | storage/installed.lock exists | as expected | PASS |
| 1. Installation | /install is locked after installation | HTTP 403 | PASS |
| 1. Installation | all required tables exist | 23 tables | PASS |
| 1. Installation | InnoDB engine | InnoDB | PASS |
| 1. Installation | utf8mb4 collation | utf8mb4_unicode_ci | PASS |
| 2. Authentication | unauthenticated /admin redirects to login | HTTP 302 | PASS |
| 2. Authentication | login form carries a CSRF token | 64 chars | PASS |
| 2. Authentication | wrong password is rejected | Invalid credentials. | PASS |
| 2. Authentication | correct password signs in | /admin | PASS |
| 2. Authentication | /admin reachable once signed in | 200 | PASS |
| 2. Authentication | API reports the signed-in user | csrf token issued | PASS |
| 3. Security | POST without a CSRF token is refused | 11 | PASS |
| 3. Security | POST with a valid CSRF token succeeds | 12 | PASS |
| 3. Security | SQL injection payloads are inert | 23 tables intact | PASS |
| 3. Security | no time-based SQL injection | 4 queries in 0.01s | PASS |
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
| 5. CRUD modules | Dashboard renders | HTTP 200, 8726b | PASS |
| 5. CRUD modules | Questions renders | HTTP 200, 39369b | PASS |
| 5. CRUD modules | Categories renders | HTTP 200, 23702b | PASS |
| 5. CRUD modules | Participants renders | HTTP 200, 9097b | PASS |
| 5. CRUD modules | Prize ladder renders | HTTP 200, 42519b | PASS |
| 5. CRUD modules | Gifts renders | HTTP 200, 11336b | PASS |
| 5. CRUD modules | Lifelines renders | HTTP 200, 16617b | PASS |
| 5. CRUD modules | Game history renders | HTTP 200, 6381b | PASS |
| 5. CRUD modules | Reports renders | HTTP 200, 14449b | PASS |
| 5. CRUD modules | Settings renders | HTTP 200, 73118b | PASS |
| 5. CRUD modules | Users renders | HTTP 200, 6272b | PASS |
| 5. CRUD modules | Backups renders | HTTP 200, 6569b | PASS |
| 5. CRUD modules | Updates renders | HTTP 200, 14136b | PASS |
| 5. CRUD modules | Audit log renders | HTTP 200, 24024b | PASS |
| 5. CRUD modules | Operator console renders | HTTP 200, 9513b | PASS |
| 5. CRUD modules | Operator setup renders | HTTP 200, 10463b | PASS |
| 5. CRUD modules | Display screen renders | HTTP 200, 7210b | PASS |
| 5. CRUD modules | Home page renders | HTTP 200, 2210b | PASS |
| 5. CRUD modules | question created | id 33 | PASS |
| 5. CRUD modules | question options stored | 4 | PASS |
| 5. CRUD modules | Gujarati text stored unchanged | એક | PASS |
| 5. CRUD modules | question updated | D | PASS |
| 5. CRUD modules | question duplicated | copy id 34 | PASS |
| 5. CRUD modules | unused question deleted | as expected | PASS |
| 5. CRUD modules | participant created | id 11 | PASS |
| 5. CRUD modules | registration number auto-assigned | as expected | PASS |
| 5. CRUD modules | prize level created | level 11 = 640000 | PASS |
| 5. CRUD modules | guaranteed flag stored | 1 | PASS |
| 5. CRUD modules | prize level deleted | as expected | PASS |
| 5. CRUD modules | gift created | id 11 | PASS |
| 5. CRUD modules | gift deleted | as expected | PASS |
| 5. CRUD modules | CSV export export | HTTP 200, UTF-8 BOM present | PASS |
| 5. CRUD modules | CSV export games.csv | HTTP 200, UTF-8 BOM present | PASS |
| 5. CRUD modules | CSV export questions.csv | HTTP 200, UTF-8 BOM present | PASS |
| 5. CRUD modules | CSV export prizes.csv | HTTP 200, UTF-8 BOM present | PASS |
| 6. Game engine | game created | Game created. | PASS |
| 6. Game engine | initial state | PARTICIPANT_INTRO | PASS |
| 6. Game engine | game starts at level 1 | 1 | PASS |
| 6. Game engine | state after start | QUESTION_DISPLAYED | PASS |
| 6. Game engine | a question is on air | ગણેશ ચતુર્થી કયા મહિનામાં ઉજવવામાં આવે છ | PASS |
| 6. Game engine | timer is loaded but not running | 30000ms | PASS |
| 6. Game engine | timer starts | running | PASS |
| 6. Game engine | timer counts down in real time | 29388ms left of 30000ms after 600ms | PASS |
| 6. Game engine | timer pauses | TIMER_PAUSED | PASS |
| 6. Game engine | paused timer does not drift | held at 29377ms | PASS |
| 6. Game engine | timer resumes | running | PASS |
| 6. Game engine | timer resets to full | 30000 | PASS |
| 6. Game engine | 50:50 removes two options | 2 | PASS |
| 6. Game engine | 50:50 keeps the correct answer | B,D kept | PASS |
| 6. Game engine | a lifeline cannot be used twice | The 50:50 lifeline has already been used. | PASS |
| 6. Game engine | audience poll totals 100% | 100 | PASS |
| 6. Game engine | poll gives 0% to options 50:50 removed | as expected | PASS |
| 6. Game engine | expert suggests a surviving option | suggested D | PASS |
| 7. Display screen security (the critical requirement) | correct answer is null before the reveal | present and null | PASS |
| 7. Display screen security (the critical requirement) | explanation is null before the reveal | present and null | PASS |
| 7. Display screen security (the critical requirement) | no "private" section in the display payload | as expected | PASS |
| 7. Display screen security (the critical requirement) | the string "correct_option" appears nowhere | as expected | PASS |
| 7. Display screen security (the critical requirement) | no password or token data anywhere | as expected | PASS |
| 7. Display screen security (the critical requirement) | options removed by 50:50 are null, not hidden client-side | A = null | PASS |
| 7. Display screen security (the critical requirement) | the operator DOES receive the correct answer at the same moment | operator sees B | PASS |
| 8. Answer flow | an option removed by 50:50 cannot be selected | Option A was removed by the 50:50 lifeline. | PASS |
| 8. Answer flow | option selected | D | PASS |
| 8. Answer flow | selection can be changed before locking | B | PASS |
| 8. Answer flow | reveal is refused before the answer is locked | Lock the answer before revealing the result. | PASS |
| 8. Answer flow | answer locks | locked | PASS |
| 8. Answer flow | locking stops the timer | timer stopped | PASS |
| 8. Answer flow | a locked answer cannot be changed | The answer is locked. Use the override control to change it. | PASS |
| 8. Answer flow | correct answer STILL hidden after locking | still null | PASS |
| 8. Answer flow | display shows the answer as locked | as expected | PASS |
| 8. Answer flow | operator override unlocks the answer | unlocked | PASS |
| 8. Answer flow | the override is written to the audit log | 6 | PASS |
| 8. Answer flow | correct answer gives state CORRECT | CORRECT | PASS |
| 8. Answer flow | prize awarded for level 1 | 500 | PASS |
| 8. Answer flow | correct answer IS released after the reveal | B | PASS |
| 8. Answer flow | explanation released after the reveal | as expected | PASS |
| 9. Two screen synchronisation | state_version advances on every operator action | 15 -> 16 | PASS |
| 9. Two screen synchronisation | operator state endpoint responded | Current game state. | PASS |
| 9. Two screen synchronisation | both screens agree on the level | 2 | PASS |
| 9. Two screen synchronisation | both screens agree on the question | ભગવાન ગણેશનું વાહન કયું છે? | PASS |
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
| 12. Game reset and completion | unused questions are tracked across games | 9 unused of 15 active | PASS |
| 12. Game reset and completion | clearing the ladder completes the game | completed | PASS |
| 12. Game reset and completion | final prize is the top prize | 320000 | PASS |
| 12. Game reset and completion | questions answered | 10 | PASS |
| 12. Game reset and completion | question-reuse setting restored | false | PASS |
| 12. Game reset and completion | timer expires without any client action | TIME_UP | PASS |
| 12. Game reset and completion | time up is recorded as a timeout | timeout | PASS |
| 12. Game reset and completion | time up ends the game | time_up | PASS |
| 13. Backup and restore | database backup created | backup_2026-09-12_10-03-02_db.zip (16.1 KB) | PASS |
| 13. Backup and restore | backup contains a manifest | as expected | PASS |
| 13. Backup and restore | dump contains every table | 23 CREATE TABLE statements | PASS |
| 13. Backup and restore | data destroyed before the restore | as expected | PASS |
| 13. Backup and restore | restore brings the rows back | 15 | PASS |
| 13. Backup and restore | a safety backup was taken before restoring | backup_2026-09-12_10-03-02-1_db.zip | PASS |
| 13. Backup and restore | Gujarati text survives backup and restore byte for byte | ગણેશ ચતુર્થી કયા મહિનામાં ઉજવવામાં આવે છે? | PASS |
| 13. Backup and restore | files backup created | backup_2026-09-12_10-03-02_files.zip (274.08 KB, 179 files) | PASS |
| 13. Backup and restore | files backup contains the application | as expected | PASS |
| 13. Backup and restore | files backup excludes existing backups | as expected | PASS |
| 14. Migrations, cache and the updater | no migrations are pending | [] | PASS |
| 14. Migrations, cache and the updater | migration history is recorded | 6 applied | PASS |
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
| 15. Audit log | logged: login | 18 entries | PASS |
| 15. Audit log | logged: game.created | 14 entries | PASS |
| 15. Audit log | logged: game.answer_locked | 79 entries | PASS |
| 15. Audit log | logged: game.result_revealed | 76 entries | PASS |
| 15. Audit log | logged: game.reset | 4 entries | PASS |
| 15. Audit log | logged: question.created | 13 entries | PASS |
| 15. Audit log | audit entries record an IP address | 220 entries with an IP | PASS |
| 15. Audit log | no secrets written to the audit log | as expected | PASS |
| 16. Responsive and accessibility markup | Admin declares a viewport | as expected | PASS |
| 16. Responsive and accessibility markup | Operator declares a viewport | as expected | PASS |
| 16. Responsive and accessibility markup | Display declares a viewport | as expected | PASS |
| 16. Responsive and accessibility markup | admin CSS has mobile breakpoints | 4 media queries | PASS |
| 16. Responsive and accessibility markup | admin CSS sets 16px inputs on mobile (prevents iOS zoom) | as expected | PASS |
| 16. Responsive and accessibility markup | display CSS scales with the viewport (vmin units) | 183 vmin values | PASS |
| 16. Responsive and accessibility markup | display CSS respects reduced motion | as expected | PASS |
