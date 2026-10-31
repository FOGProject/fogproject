# U2-04 P2: B01–B39 / B39細分 実行分類

## 目的と判定規則

この文書は全scopeの障害・競合受入を実行可能な単位へ分類する計画であり、実行結果ではない。ここに挙げるケースは**すべて未実行**であり、既存テストのPASS、製品実装の存在、静的なguard検出をbarrier PASSへ読み替えない。

| 区分 | 使い方／PASSに必要な証拠 |
|---|---|
| **SQL-B** | 隔離scratch MariaDBとサポート対象MySQLで実スキーマを使用。独立PHP process・独立PDO・明示ready/continue barrierを使い、実際のSQL、COMMIT、行値/hash、Task/high-water/intent/markerを前後照合する。stub DB・SQLite・sleep競合では代替しない。 |
| **SQL-F** | 実PDO/SQLを維持し、GET_LOCK、接続断、COMMIT/RELEASE応答喪失等だけを狭いfault seamで制御する。DBが実際にCOMMITしたか不明な場合は実DBを再読し、未実行とは記録しない。 |
| **SQL+stub** | 状態・writer・fence・結果保存はscratch DB上の実SQL。LDAP、native API、plugin/hookの競合タイミングだけは決定的stub。stub呼出回数とDB outcomeを別々に保存する。 |
| **stub/unit補助** | pure helper・compiler・render・protocol応答の既存回帰に再利用可能。ただしController/Route/Manager実SQL、別processの順序、耐久性を証明しない。対応するSQL-B/SQL-Fがある場合に限り補助証拠とする。 |

既存候補の再利用対象は `agent-directory-operation-db.test.php`、`agent-directory-retry-protection-db.test.php`、`agent-directory-observation-placement-db.test.php`、`agent-directory-local-marker-db.test.php`、`agent-directory-verification-sc-db.test.php`、`agent-directory-task.test.php`、`agent-directory-placement.test.php`、`agent-directory-operation.test.php`、`agent-directory-observation.test.php`、`agent-directory-join.test.php`、`route-write-path-guards.test.php`、`host-ad-ui-save.test.php`、`mass-edit-fails-closed.test.php`、`mass-edit-endpoint-is-gated.test.php`、`mass-edit-form.test.php`、`mass-edit-from-group.test.php`、`foreign-key-map.test.php`、`foreign-key-reconcile.test.php`、`schema-executes.test.php`、`schema-upgrade-replay.test.php` とする。これらは現行の機能・形状・局所契約を回帰する材料で、列挙だけでは新writer barrierをカバーしない。実行runner/fixtureの接続は実装後に確認する。

**結果の分離記録（全SQL-B/SQL-F/SQL+stub）**: 各scenarioの`load outcome`、`HTTP poll outcome`、`server claim outcome/reason`、`agent local-unknown marker`を別列・別countで記録する。claim拒否をAgent local unknownとして集計しない。PHP-onlyケースはlocal markerを「未計測/適用外」と明記し、Agent bridgeを含む統合ケースのみmarkerの有無・attempt ID・raw hashを確認する。LDAP/native stub dispatch countもSQL commit countと混ぜない。

## B01–B39: writer/fence barrier

| ID | 再利用する既存回帰（補助） | 必須の実行分類・新規barrier | 観測・合格条件（未実施） |
|---|---|---|---|
| B01 | `agent-directory-operation-db.test.php`, `agent-directory-task.test.php` | SQL-B: A start後claim前で停止、B rename/saveを別PDOでCOMMIT、Aを再開 | Aのclaim拒否、Task未消費・startedなし・native/placement dispatch 0、BのHost/関連行を照合。 |
| B02 | 同上、`host-ad-ui-save.test.php` | SQL-B: A claim COMMIT後・native前にBの旧名保存 | B保存拒否、Bの全対象行不変。Aの元認可／Task/high-water保持。 |
| B03 | 上記 | SQL-B: B fence前／取得後を明示barrierで反転 | 到着順でなく実lock/COMMIT勝者の片方だけ成立。両方許可・両方破壊は失敗。 |
| B04 | `agent-directory-operation-db.test.php`, `agent-directory-verification-sc-db.test.php` | SQL-B+stub: 同Host二重claimと異Host同Target GUID/SIDを同時実行 | 最大1 claim/permit。競合側Task/native 0。GUID-only/SID-only衝突も拒否。 |
| B05 | `agent-directory-operation-db.test.php`, `agent-directory-placement.test.php` | SQL+stub: plan後のTarget変更、消失・同名再作成をstubで返す。SQLのclaimは実行 | Snapshot/GUID/SID不一致でclaim・dispatch 0、原本保持。外部変更後の限界はB38へ分離。 |
| B06 | `agent-directory-verification-sc-db.test.php`, `agent-directory-retry-protection-db.test.php` | SQL-B: report COMMIT直前/直後をbarrier、B保存を競合 | verified前は拒否、validated verified後は通常保存可。HD履歴/high-waterを両方確認。 |
| B07 | `agent-directory-retry-protection-db.test.php`, `agent-directory-operation-db.test.php` | SQL-B: protected retryと別Host renameを同時制御 | D1の拒否期待でstate/attempt/Target/high-water raw値不変、競合rename拒否。旧Retryテストは単体証拠のみ。 |
| B08 | `agent-directory-retry-protection-db.test.php`, `host-ad-ui-save.test.php` | SQL+stub: ready/verified Retry正例、claimed中Target変更負例。UI/APIを実経路で実行 | CSRF/認可、response、DB state、historyを確認。P1 Retry回帰の合格とbarrier結果は別記録。 |
| B09 | `host-ad-ui-save.test.php`, `route-write-path-guards.test.php` | SQL-B: stale Host object保持→別接続で現名変更→stale全面save | protected Hostへの旧名書戻し0。dirty true/false両方。inactive対照の既存契約維持。 |
| B10 | `agent-directory-operation-db.test.php`, `foreign-key-map.test.php` | SQL-B: PK/UNIQUEが異なる保護行へ解決するupsert、HD owner upsert、name swap | 実PK候補全件を評価し曖昧更新0。inactive対照は既存SQLの戻り/失敗契約と比較。 |
| B11 | `agent-directory-retry-protection-db.test.php`, `foreign-key-map.test.php` | SQL-B: claimed HD reparent/PK upsert、旧・新owner別状態 | 両ownerと全unique候補を実SQL前に検査し拒否。verified権威行もD2に沿って不変。 |
| B12 | `route-write-path-guards.test.php`, `mass-edit-fails-closed.test.php` | SQL-B: `FOGManagerController::update`実SQL。WHERE空/LIKE/IN/空配列/ORを個別投入 | 元compilerの実対象集合とguard集合を比較。protected変更0。非敏感tableは既存結果維持。 |
| B13 | `agent-directory-operation-db.test.php`, `agent-directory-local-marker-db.test.php` | SQL-F+stub: claim前、claim COMMIT中/後、native stub中にprocess kill/fault | 未COMMIT rollbackとCOMMIT後保護を別期待にする。phase/marker/Task/high-waterを採取。時間経過だけで解除しない。 |
| B14 | `foreign-key-map.test.php`, `foreign-key-reconcile.test.php`, `route-write-path-guards.test.php` | SQL-B: HD JSON DELETE、instance destroy、非unique destroy、Route mass deleteを各実経路で実行 | 存続Hostのauthority rowは保持。facts-only legacy HD削除は正例。HTTP statusだけで判定しない。 |
| B15 | `host-ad-ui-save.test.php`, `mass-edit-form.test.php`, `foreign-key-map.test.php` | SQL-B: non-Host、inactive Host、同owner facts、同値save、全体decommission対照 | 元permission・SQL result・正常decommission/legacy削除を確認。verified/high-water HD単独削除はここに混ぜずB14/B35で拒否。 |
| B16 | `agent-directory-task.test.php`, `agent-directory-operation-db.test.php` | SQL-B+HTTP fixture: task_id=0/Taskあり/消費済/期限/差替/start ACK喪失 | high-water・one-shot条件を保持。新fence経由で別Task/manual Retry fallback 0。response/load結果はclaim結果と別記録。 |
| B17 | `agent-directory-operation-db.test.php` | SQL-F: fence後`PDODB::disconnect`、ping失敗、別PDO差替 | 捕捉PDO identity違反で対象SQL 0。旧lockを新PDO上の権限と扱わない。 |
| B18 | `agent-directory-verification-sc-db.test.php`, `agent-directory-local-marker-db.test.php` | SQL+stub: native success→awaiting→postboot→verifiedの段階停止 | awaiting中hold維持、verified後のみnative fence解除。履歴verifiedを後続SC=Falseで再保護しない。 |
| B19 | `agent-directory-operation-db.test.php` | SQL-F: 実GET_LOCK 1/0/NULL/例外/取得中切断。取得不能分岐は明示fault seam | 1以外の敏感SQL/claim 0。error/timeout/result型を個別採取。 |
| B20 | 同上 | SQL-F: COMMIT成功、自前ROLLBACK、COMMIT ACK喪失 | release処理・transaction owner確認。ACK喪失は実DB再読し、claim/retryの重複0。 |
| B21 | 同上 | SQL-F: COMMIT後release 1/0/NULL/例外 | release不成功で要求中断/poison。既にcommitされた行を保持し、再保存・native再実行0。 |
| B22 | 同上 | SQL-F: 同PDO logical再入、contextなし残留lock、別PDO再入 | GET_LOCK二重取得0、outer release一回。残留自己所有/別PDO再入を拒否。 |
| B23 | `agent-directory-operation-db.test.php`, `agent-directory-observation-placement-db.test.php` | SQL-B+stub: empty/ready/verified/legacy-incomplete/破損JSON/欠損Targetを実stateへ設定 | inactiveを過剰保護しない。不確実stateは識別可能な敏感writerだけを止め、元unknownを維持。 |
| B24 | `agent-directory-operation-db.test.php`, `foreign-key-map.test.php` | SQL-B: HD同owner facts upsert、stale owner、未採番INSERTとunique競合 | 必須INSERT列保持。factsでstate/config/high-water巻戻しなし。stale reparent拒否。 |
| B25 | `mass-edit-fails-closed.test.php`, `mass-edit-form.test.php`, `agent-directory-operation-db.test.php` | SQL-B: Manager複数payload/JSON joining bulkの後方に保護対象を配置 | 最初の敏感SQL・association変更前にpreflight。guard拒否時の先行更新0。通常bulk partial successはB33/B28と分離。 |
| B26 | `route-write-path-guards.test.php`, `mass-edit-fails-closed.test.php` | SQL-B: preflight SELECT後に別接続でpredicateへ新Hostを流入 | 実UPDATEに検査済PK AND 元WHEREを適用。未検査行の更新0、predicate意味維持。 |
| B27 | `mass-edit-fails-closed.test.php`, `agent-directory-operation-db.test.php` | SQL-B: 501件超batch、保護対象は後半chunk。table overrideも実行 | 全chunkを最初のSQL前にpreflight。競合時は先行chunk SQL 0、fence共有。 |
| B28 | `mass-edit-fails-closed.test.php` | SQL-F: preflight後、実DBの後半chunkへ一般SQL制約違反を注入 | 既存非atomicの先行COMMITを記録。atomicだったと誤記せず、安全な再送を推定しない。 |
| B29 | `foreign-key-map.test.php`, `foreign-key-reconcile.test.php`, `route-write-path-guards.test.php` | SQL-B+hook stub: active/inactive Host mass-deleteを最初のhook/cascade境界で停止 | activeはhook/child/parent DELETE 0。inactiveは既存順序と関連行cascade成功。 |
| B30 | `foreign-key-map.test.php`, `foreign-key-reconcile.test.php` | SQL-B: inactive Host全体削除と、Host存続HD単独削除を同じfixtureで比較 | 許可はguard済みparent ID配下のcascadeのみ。他HostのHD削除0。 |
| B31 | `route-write-path-guards.test.php`, `agent-directory-operation-db.test.php` | SQL-B+hook stub: hookが対象を追加/変換、nested CRUD/claimを起動 | hook前とhook後に実PK/table/owner再評価。追加保護対象のDELETE 0、generic mutation内claim再入拒否。 |
| B32 | `agent-mj1-route-permissions.test.php`, `route-write-path-guards.test.php`, `agent-directory-operation-db.test.php` | HTTP/route auth tests補助 + SQL-B: CSRF、role、object scope、JSON HD owner/DELETE | auth/scope拒否をfence前に検証。scope対象IDとerror mappingを記録。unauthorized responseからstate情報を漏らさない。 |
| B33 | `mass-edit-form.test.php`, `mass-edit-from-group.test.php`, `mass-edit-endpoint-is-gated.test.php`, `agent-directory-observation-placement-db.test.php` | SQL-B: Web mass edit、JSON joining bulk、plugin field、Enrollment/factsを実経路で実行 | name欄新設なし。非sensitive操作と既存partial-successを維持。load/poll/claim/local marker別計数。 |
| B34 | `agent-directory-operation-db.test.php`, `agent-directory-placement.test.php` | SQL+stub: A Create(C)とB Existing(X)を両方claimし、native stub完了前で待機 | 別Target双方許可、native overlapをstubで証明。短いDB直列化がstub処理区間を直列化しない。実AD並列とは主張しない。 |
| B35 | `agent-directory-retry-protection-db.test.php`, `foreign-key-map.test.php` | SQL-B: verified/high-water HDだけを失わせる反例と候補拒否経路 | authority row消去/reparent/置換を拒否し、high-water/attempt/Target raw hash不変。HD履歴なし再適格化を拒否。 |
| B36 | `route-write-path-guards.test.php`, `foreign-key-map.test.php`, `mass-edit-fails-closed.test.php` | SQL-B: Manager table override、Controller非ID destroy、Host/HD PK変更 | PHP classnameでなく実table/column/PK集合をguard。非Host SQL挙動は対照保持。 |
| B37 | `agent-directory-operation-db.test.php` | SQL-F: 外側transaction、autocommit=0、逆順row-lock/deadlock/3秒timeout | 自動retryなし。自前transactionだけrollback。Task/native authority保持。正常再入/異常再入を別scenarioとして採取。 |
| B38 | `agent-directory-placement.test.php`, `agent-directory-observation-placement-db.test.php` | SQL+stub: 最終LDAP read後に外部AD管理者が同名objectを切替えるstub schedule | 実DB fenceだけで外部変更を防いだと主張しない。対象照合・送信停止範囲と残るAPI原子性限界を記録、repair/native再実行0。 |
| B39 | `agent-directory-placement.test.php`, `agent-directory-operation-db.test.php` | SQL-B+stub: placement gate後に別FOG writerがname再割当/Existing claim、旧GUID Moveを再開 | 別writerを外部AD変更と取り違えない。intent CAS/permitと実SQLで勝者を確定。同Target競合側のMove0、別Target対照は継続。細分は下表30件すべて実行。 |

## B39細分: 配置intent・LDAP dispatch・未知結果

| ID | 再利用回帰 | 必須実行／stub対実SQL | 判定・個別計数 |
|---|---|---|---|
| B39-01 | `agent-directory-placement.test.php`, `agent-directory-observation-placement-db.test.php` | SQL+LDAP stub: legacy/protocol2 drift/正しいOU | driftはGUID/SID確認後1 Move、既配置0。正常配置停止をPASSにしない。 |
| B39-02 | `agent-directory-observation.test.php`, `agent-directory-observation-placement-db.test.php` | pure freshness再利用 + SQL+stub: protocol2の欠落/期限/revision/False | protocol2 PREPARED/dispatch 0、復帰後正常Move。legacy gateは不変。 |
| B39-03 | `agent-directory-operation-db.test.php` | SQL-B+LDAP stub: lookup後、B rename COMMIT、A PREPARED試行 | A拒否、Task/native/Move 0。responseとclaim拒否を別記録。 |
| B39-04 | `agent-directory-operation-db.test.php`, `agent-directory-local-marker-db.test.php` | SQL-B+stub: B claim先行、A PREPARED/dispatchを再開 | native protected phasesでA Move 0。native stateをplacement用に書換えない。 |
| B39-05 | `agent-directory-operation-db.test.php` | SQL-B+stub: A PREPARED停止、B取消CAS/name save後A再開 | CANCELLED勝利、permit/LDAP 0、B save可。単純DELETEで代用しない。 |
| B39-06 | 同上 | SQL-B+stub: A dispatch MAY commitがB save/claimに先行 | B拒否。A世代dispatch最大1。 |
| B39-07 | 同上 | SQL-B+stub: 複数PHP processが同slot dispatch CAS | permit/dispatch勝者ちょうど1、敗者0。同GUID/SIDを別Hostでも競合させる。 |
| B39-08 | 同上 | SQL-F+stub: PREPARED worker crash、別worker再開 | PREPAREDだけfresh再検証後に取消/再準備可。MAYをPREPAREDへ戻さない。 |
| B39-09 | `agent-directory-operation-db.test.php` | SQL-F: MAY COMMIT ACK遮断 | 全worker LDAP 0。行が存在してもpermit再構築・再送0。 |
| B39-10 | 同上 | SQL-F: MAY後のrelease失敗/PDO差替 | LDAP 0、durable hold保持。old PDO lockをnew PDO継続根拠にしない。 |
| B39-11 | 同上、`agent-directory-local-marker-db.test.php` | SQL-F+stub: MAY後LDAP前worker kill、restart/poll/native claim | 実効果0でもhold。再送・解除0。server phaseとAgent local markerは別々に採取。 |
| B39-12 | `agent-directory-operation-db.test.php`, `agent-directory-placement.test.php` | SQL-F+LDAP stub: stub処理中DB接続killしB save/claim、別GUID操作を並行 | 同Target B拒否を実DBで確認。別Target facts/Moveは許可。stub outcomeとSQL commitを独立記録。 |
| B39-13 | `agent-directory-placement.test.php` | SQL+stub: Move適用後、stubがtimeout/通信断/例外を返す | UNKNOWN/MAY hold、再送0、SC=Trueでも解除なし。stubは外部結果の曖昧さのみ表現。 |
| B39-14 | 同上 | SQL+stub: LDAP送信後の確定拒否と送信前connect/search失敗を別投入 | 送信後非成功は初版契約どおり保留/非再送。送信前失敗と同一分類にしない。 |
| B39-15 | `agent-directory-operation-db.test.php` | SQL-F+stub: LDAP成功応答後、結果COMMIT ACK喪失 | 追加LDAP 0。DB実値を再読しterminal不明なら他writer hold。 |
| B39-16 | U2-05 H-02で改訂 | SQL-F+stub: success response後のDB断／PDO poison、健全な元PDOのCOMMIT前例外 | DB断・結果不明では新PDOへ切り替えずMAY_HAVE_RUN／UNKNOWNと同GUID/SID・世代・payloadを保持。元の健全PDOだけ同一receipt／同一結果の保存再試行を許可。COMMIT済みSUCCEEDEDは維持し、LDAP再送・permit再生成0。 |
| B39-17 | 同上、`agent-directory-local-marker-db.test.php` | SQL-F+stub: success response後、元worker crash | 別workerのOU readだけで解除しない。管理HOLD状態。local unknown markerとは別count。 |
| B39-18 | `agent-directory-operation-db.test.php`, `agent-directory-observation-placement-db.test.php` | SQL+stub: HTTP重送、重複facts、古いcallback、新drift | 同要求/旧世代Move 0。freshな真のdriftだけ新世代に進む。poll response/claim/local marker分離。 |
| B39-19 | `agent-directory-placement.test.php` | SQL+LDAP stub: source/destination DN再利用、Target消失/再作成 | GUID entry/親指定で他GUIDを動かさない。stubはLDAP対象を保証するだけで、intent排他はSQL側で証明。 |
| B39-20 | `agent-directory-placement.test.php`, `agent-directory-operation-db.test.php` | SQL-B+stub: GUID-only/SID-only衝突、domain alias | 別slot化せず拒否。Host ID/protocol差で迂回不可。 |
| B39-21 | `agent-directory-operation-db.test.php`, `agent-directory-placement.test.php` | SQL-B+stub: legacy placement→MJ1 claim、逆順、別Target対照 | 同Targetは勝者のみ。別Targetは並行可能。claim rejection/local unknown/LDAP dispatchを別カウント。 |
| B39-22 | `agent-directory-placement.test.php`, `agent-directory-observation-placement-db.test.php` | SQL+LDAP stub: DN欠落、machineAccount lookup、Linux/hostname changerなし、DIRECTORY_CONNECTION | 正常identity解決は1回Move。曖昧/不完全/別Host矛盾は0。plugin connection選択も既存契約回帰。 |
| B39-23 | `route-write-path-guards.test.php`, `agent-directory-retry-protection-db.test.php` | SQL-B+HTTP/API: PREPARED/MAY/UNKNOWN中のname/domain/OU/owner変更 | PREPARED取消勝者のみ許可。MAY/UNKNOWN対象変更拒否。通常facts更新は継続。 |
| B39-24 | `foreign-key-map.test.php`, `foreign-key-reconcile.test.php`, `route-write-path-guards.test.php` | SQL-B: HD DELETE/reparent、Host delete/FK cascade、競合順序を実SQLで反転 | D2履歴保持。MAY/UNKNOWNはhook/cascade前に拒否。Host delete先行なら旧worker send 0。 |
| B39-25 | `host-ad-ui-save.test.php`, `foreign-key-map.test.php` | SQL-B: SUCCEEDED後inactive decommission、同値save、non-Host CRUD | 正常操作可、terminal generationは保持、Host ID再利用を旧owner認可にしない。 |
| B39-26 | `mass-edit-fails-closed.test.php`, `route-write-path-guards.test.php` | SQL-B+hook stub: 501件batch後半に競合、plugin hookが対象追加 | 最初の敏感SQL前に全候補検査。既存partial successとの違いを別scenarioで評価。 |
| B39-27 | `agent-directory-operation-db.test.php` | SQL-F: GET_LOCK失敗/null/deadlock、逆順lock、破損payload/schema不足 | retry/Move 0。通常factsに及ぶ停止範囲も採取し、global stopを主張しない。 |
| B39-28 | `agent-directory-placement.test.php` | SQL+stub: `FOGLdap::moveTo`直呼び、偽/消費済/別世代permit | 最終LDAP送信入口で拒否。Controller callsite単体stub testだけではPASS不可。 |
| B39-29 | `schema-upgrade-replay.test.php`, `schema-executes.test.php`, `agent-directory-operation-db.test.php` | scratch schema SQL-B+upgrade fixture。旧worker/in-flightは制御stubで表現 | 旧要求不明/旧worker残存ならUpgrade拒否。新表空だけでclean boundaryを認定しない。 |
| B39-30 | `schema-executes.test.php`, `schema-upgrade-replay.test.php` | SQL-B: fresh/upgrade/途中DDL失敗・再実行/旧schema復元。公式migrationは対象外 | 完全なscratch schemaでのみ処理開始。intent保持、partial schemaではLDAP 0、旧DB復元でunknownを消した継続不可。 |

## 実行パッケージと記録様式

1. 各SQL-B/SQL-FはMariaDBとサポート対象MySQLで別々に実行し、実行DB名・schema hash、worker PID、PDO/`CONNECTION_ID()`、UTC、fence取得/COMMIT/RELEASE境界、SQL/error、対象PK集合、前後row hashを保存する。実FOG DB・AD・A/Bには接続しない。
2. 同一シナリオに旧順序と候補順序を置く場合も、旧版の穴を再現するfixture結果と候補の拒否結果を分ける。`sleep`やtimeout単独をbarrier証明にしない。各workerのready/continue信号にwatchdogを付ける。
3. 実SQLを要する項目をORM stub、query responder、SQLite、source-order assertionだけでPASSにしない。stubで代用するのはLDAP/native/hookの外部タイミングであり、その間のfence・intent・CRUD・履歴更新は実scratch SQLにする。
4. 各実行結果行は`未実行 / PASS / FAIL / BLOCKED`のいずれかと証拠URIを持つ。現時点の全B01–B39/B39-01–30は`未実行`。既存テストの過去結果は「再利用可能な回帰根拠」として参照し、今回のbarrier結果欄へ転記しない。
5. load、poll、claim failure、Agent local unknownを独立に集計する。推奨列: `load_status/reason`、`poll_http_status/reason`、`claim_attempt_id/reason/task_consumed`、`local_marker_phase/attempt_id/raw_hash`、`ldap_stub_dispatch_count`、`intent_generation/status`。該当層を実行していないケースは0ではなく`N/A`とする。claim失敗をlocal unknownへ合算せず、server UNKNOWNとAgent marker unknownも同一状態として扱わない。
6. PASSには期待どおりのAPI応答だけでなく、意図したSQL副作用、原本不変条件、dispatch回数、競合側副作用0をすべて照合する。COMMIT ACK喪失・既存partial commitでは、DB再読値を記録して「未実行」に書き換えない。

