# 📡 [DOCS 02] x402 프로토콜 통신 규격서
> **프로젝트명**: Word402 (WordPress x402 Agentic Monetization Plugin)  
> **문서 버전**: v1.0  
> **작성일**: 2026-09-09  
> **기반 표준**: x402 Specification (RFC 9110 HTTP 402 Extension)

---

## 1. 개요 (Specification Overview)

본 문서는 워드프레스 서버(`Word402 플러그인`)와 AI 에이전트 클라이언트 간에 오가는 **HTTP 402 Payment Required 핸드셰이크 프로토콜의 헤더, 바디, 상태 코드 및 에러 처리 표준 규격**을 정의합니다.

* **프로토콜 명칭**: `x402-http-agent-spec`
* **버전**: `2.0`
* **통신 포맷**: HTTP Header 및 JSON / Markdown
* **기본 결제 화폐**: USDC (Base Sepolia L2)

---

## 2. 엔드포인트 규격 (API Endpoints)

### 2.1. 포스트 조회 엔드포인트
```http
GET /wp-json/word402/v1/posts/{id}
```
* **설명**: 특정 포스트의 본문을 요청합니다. 유료 글인 경우 결제가 확인되지 않으면 `402 Payment Required`를 반환합니다.
* **Content Negotiation**: 일반 웹 브라우저는 기존 워드프레스 URL(`https://example.com/p/{id}`)로 접근하며, AI 에이전트는 전용 REST API 엔드포인트로 접근하거나 `Accept: text/markdown` 헤더를 첨부합니다.

---

## 3. 요청 및 응답 메시지 규격 (Message Specifications)

### 3.1. [Step 1] 에이전트의 최초 데이터 요청 (Initial Request)

에이전트는 데이터 조회를 위해 표준 GET 요청을 전송합니다.

#### 요청 헤더 (Request Headers)
```http
GET /wp-json/word402/v1/posts/42 HTTP/1.1
Host: your-wordpress-site.com
Accept: text/markdown, application/json
User-Agent: MyAgentClient/1.0 (Autonomous Web3 Agent)
```

---

### 3.2. [Step 2] 서버의 결제 요구 응답 (HTTP 402 Payment Required)

서버는 유료 글임을 확인하고, 클라이언트가 결제해야 할 세부 내역을 헤더와 JSON 바디로 반환합니다.

#### 응답 헤더 (Response Headers)
```http
HTTP/1.1 402 Payment Required
Content-Type: application/json; charset=utf-8
WWW-Authenticate: x402 network="base-sepolia", currency="USDC", amount="0.005", recipient="0x71C...B29", challenge="chn_9f8a7c2b4e"
X-402-Version: 2.0
X-402-Challenge-ID: chn_9f8a7c2b4e
X-402-Expires-At: 1725901200
```

#### 응답 바디 (Response Body - JSON)
```json
{
  "protocol": "x402",
  "version": "2.0",
  "status": "payment_required",
  "resource": {
    "post_id": 42,
    "title": "2026 AI Agentic Commerce Deep Dive Report",
    "teaser": "본 리포트는 2026년 급부상한 AI 에이전틱 커머스 시장의 구조와...",
    "content_type": "text/markdown"
  },
  "payment_requirements": {
    "network": "base-sepolia",
    "chain_id": 84532,
    "currency": "USDC",
    "token_address": "0x036CbD53842c5426634e7929541eC2318f3dCF7e",
    "decimals": 6,
    "amount": "0.005",
    "amount_raw": "5000",
    "recipient": "0x71C...B29",
    "challenge_id": "chn_9f8a7c2b4e",
    "expires_at": 1725901200,
    "ttl_seconds": 300
  }
}
```

* **필드 설명**:
  * `chain_id`: 84532 (Base Sepolia 테스트넷 ID)
  * `token_address`: Base Sepolia 공식 테스트넷 USDC 컨트랙트 주소
  * `amount_raw`: 소수점 6자리를 감안한 온체인 스마트컨트랙트 전송 단위 (`5000` = 0.005 USDC)
  * `challenge_id`: 이번 결제 트랜잭션을 식별하기 위한 서버 발급 단회성 식별자
  * `expires_at`: 챌린지 만료 유닉스 타임스탬프 (발급 후 5분)

---

### 3.3. [Step 3] 에이전트의 결제 증명 제출 요청 (Payment Proof Submission)

에이전트는 온체인으로 USDC 송금 트랜잭션을 실행한 뒤, 획득한 트랜잭션 해시를 헤더에 포함하여 동일한 URL로 재요청합니다.

#### 요청 헤더 (Request Headers)
```http
GET /wp-json/word402/v1/posts/42 HTTP/1.1
Host: your-wordpress-site.com
Accept: text/markdown
X-Payment: {"tx_hash":"0x3d4a8e...1b9c","challenge_id":"chn_9f8a7c2b4e","sender":"0xAgentWallet..."}
```

* **대체 헤더 표준 (Authorization 헤더 지원)**:
  * `Authorization: x402 tx_hash="0x3d4a8e...", challenge_id="chn_9f8a7c2b4e"`

---

### 3.4. [Step 4] 결제 승인 및 데이터 제공 (HTTP 200 OK)

서버는 온체인 영수증을 확인한 후, 깨끗하게 정제된 Markdown 본문을 내려줍니다.

#### 응답 헤더 (Response Headers)
```http
HTTP/1.1 200 OK
Content-Type: text/markdown; charset=utf-8
X-402-Status: settled
X-402-Tx-Hash: 0x3d4a8e...1b9c
X-402-Settled-At: 1725900950
```

#### 응답 바디 (Response Body - Clean Markdown)
```markdown
# 2026 AI Agentic Commerce Deep Dive Report

## 요약
본 리포트는 2026년 급부상한 AI 에이전틱 커머스 시장의 구조와 주요 프로토콜을 분석합니다.

## 1. 서론
전통적인 웹의 리퍼럴 광고 구조가 붕괴하면서, AI 에이전트가 데이터 가치에 직접 지불하는 x402 프로토콜이 새로운 표준으로 부상했습니다...
(전체 본문 데이터 제공)
```

---

## 4. 에러 코드 및 예외 처리 규격 (Error Handling)

결제 검증 실패 시 명확한 사유를 기계가 파싱할 수 있는 표준 JSON 에러 포맷으로 반환합니다.

```json
{
  "error": true,
  "code": "ERROR_CODE",
  "message": "인간 가독형 설명 메시지",
  "details": {}
}
```

| HTTP Status | Error Code | 설명 및 대응 방법 |
| :--- | :--- | :--- |
| **402** | `CHALLENGE_EXPIRED` | 챌린지 유효시간(5분)이 초과됨. 에이전트는 새로운 402를 받아 재시도해야 함. |
| **402** | `INSUFFICIENT_AMOUNT` | 송금액이 요구 금액보다 적음 (예: 0.005 요구했으나 0.001 송금됨). |
| **402** | `INVALID_RECIPIENT` | 수취 지갑 주소가 블로그 운영자 주소와 불일치함 (오송금). |
| **409** | `REPLAY_ATTACK_DETECTED` | 이미 다른 요청이나 다른 글에서 사용 완료된 트랜잭션 해시임. |
| **400** | `TX_NOT_FOUND` | 아직 블록체인 노드에 트랜잭션이 전파되지 않았거나 잘못된 해시임. |
| **400** | `TX_EXECUTION_REVERTED` | 스마트컨트랙트 트랜잭션 실행이 revert(실패)된 영수증임. |
| **503** | `RPC_NODE_ERROR` | 워드프레스 서버가 Base RPC 노드와 일시적으로 통신할 수 없음. |
