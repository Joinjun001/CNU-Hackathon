# 🚀 Word402 (CNU Hackathon)

> **워드프레스용 x402 프로토콜 기반 AI 에이전트 대상 유료 콘텐츠 공급 게이트웨이 & 자율 결제 에이전트**  
> *"AI 에이전트를 차단하지 말고, 1원 단위로 판매하라."*

---

## 📌 1. 개발 및 외부 접속 주소 (Access URLs)

현재 개발 및 온체인 연동 테스트를 위해 배포된 서비스 접속 정보입니다.

| 항목 | 프로토콜 | 접속 URL / 명령어 | 비고 |
| :--- | :--- | :--- | :--- |
| **워드프레스 메인 사이트** | HTTPS | [https://injun-cloud.duckdns.org](https://injun-cloud.duckdns.org) | Let's Encrypt SSL 자동 적용 |
| **관리자 콘솔 (WP Admin)** | HTTPS | [https://injun-cloud.duckdns.org/wp-admin](https://injun-cloud.duckdns.org/wp-admin) | 플러그인 / 글 관리 |
| **x402 REST API 엔드포인트** | HTTPS | `https://injun-cloud.duckdns.org/wp-json/word402/v1/posts/{id}` | AI 에이전트 데이터 조회용 |
| **로컬 직접 접속 (포트 8080)** | HTTP | [http://localhost:8080](http://localhost:8080) | SSH 터널링 필요 |
| **NPM 관리자 패널 (포트 81)** | HTTP | [http://localhost:8181](http://localhost:8181) | SSH 터널링 필요 |

### 💡 SSH 터널링 접속 명령어 (외부 로컬 PC 접속 시)
외부 PC에서 로컬 포트로 접속하고자 할 때 로컬 터미널에서 실행:
```bash
# 워드프레스(8080) 및 NPM 관리자(81) 동시 터널링
ssh -L 8080:localhost:8080 -L 8181:localhost:81 injun@injun-cloud.duckdns.org
```

---

## ⚙️ 2. 블록체인 및 지갑 설정 (Base Sepolia)

* **블록체인 네트워크**: Base Sepolia (EVM L2)
* **체인 ID (Chain ID)**: `84532`
* **RPC 엔드포인트**: `https://sepolia.base.org`
* **결제 토큰 (USDC)**: `0x036CbD53842c5426634e7929541eC2318f3dCF7e` (Decimals: 6)
* **기본 단가**: `0.005` USDC / 호출

### 지갑 정보 (Wallet Addresses)
* **판매자(서버 운영자) 수취 지갑**: `0xf49FA400df523A65827Cb2CB30C4A3dCBf784FdD`
* **구매자(AI 에이전트) 지갑**: `0xb045FD283d0CD215C51566161554F8B8Ccb0D999`

### 테스트넷 토큰 무료 수급 (Faucets)
* **가스비(Base Sepolia ETH)**:
  * [Coinbase Base Sepolia Faucet](https://coinbase.com/faucets/base-sepolia-faucet)
  * [Alchemy Base Sepolia Faucet](https://www.alchemy.com/faucets/base-sepolia)
* **결제 대금(USDC)**:
  * [Circle Testnet Faucet](https://faucet.circle.com/) (Network: **Base Sepolia** 선택)

---

## 🤖 3. AI 에이전트 실행 및 테스트 (Agent Client)

AI 에이전트(`agent-client/agent.py`)는 대상 글을 감지하고, HTTP 402를 받으면 온체인 결제 영수증을 만들어 본문을 획득한 뒤 실시간 AI 브리핑을 출력합니다.

### 3.1. 사전 준비 (파이썬 가상환경)
```bash
# 가상환경 활성화 (web3, requests 등 설치됨)
source venv/bin/activate
```

### 3.2. 시뮬레이션 모드 테스트 (가스비/토큰 불필요)
```bash
# 8번 포스트(충남대 테스트용 글 추가 2) 대상 테스트
python3 agent-client/agent.py --url http://localhost:8080/wp-json/word402/v1/posts/8 --simulate

# 외부 도메인 대상 테스트
python3 agent-client/agent.py --url https://injun-cloud.duckdns.org/wp-json/word402/v1/posts/8 --simulate
```

### 3.3. 온체인 실전 결제 테스트 (Base Sepolia 라이브 트랜잭션)
```bash
python3 agent-client/agent.py \
  --url https://injun-cloud.duckdns.org/wp-json/word402/v1/posts/8 \
  --key $AGENT_PRIVATE_KEY
```

---

## 🛠️ 4. 도커 인프라 관리 (Docker Services)

프로젝트 루트 디렉터리에서 실행합니다.

```bash
# 전체 서비스 백그라운드 기동
docker compose up -d

# 컨테이너 상태 확인
docker compose ps

# 워드프레스 로그 실시간 확인
docker compose logs -f wordpress

# 서비스 중지
docker compose down
```

### 컨테이너 구성
* **`word402-wordpress`**: 워드프레스 6.x + PHP 8.3 + Apache
* **`word402-mysql`**: MySQL 8.0 데이터베이스
* **`word402-cloudflared`**: Cloudflare 터널링 클라이언트
* **`nginx-proxy-manager`**: SSL 인증서 및 리버스 프록시 라우팅 (`healthcheck_default` 네트워크 공유)

---

## 📂 5. 프로젝트 디렉터리 구조

```text
CNU-Hackathon/
├── .env                  # 로컬 환경 변수 및 지갑 개인키 (Git 미추적)
├── .env.example          # 환경 변수 템플릿
├── docker-compose.yml    # 도커 오케스트레이션 정의 파일
├── agent-client/         # AI 에이전트 파이썬 클라이언트
│   ├── agent.py          # 자율 HTTP 402 결제 및 데이터 수신 에이전트
│   └── requirements.txt  # 의존성 패키지 (web3 등)
├── word402/              # 워드프레스 플러그인 본체
│   ├── word402.php       # 플러그인 엔트리포인트
│   ├── includes/         # 프로토콜 처리기, 온체인 검증기, DB 핸들러
│   └── admin/            # WP Admin 대시보드 및 포스트 메타박스
├── docs/                 # 아키텍처 및 x402 프로토콜 상세 설계 문서
└── tests/                # E2E 핸드셰이크 시뮬레이션 테스트 코드
```

---

## 🌿 6. Git 브랜치 관리
* **현재 작업 브랜치**: `injunBranch`
* **원격 저장소**: [Joinjun001/CNU-Hackathon](https://github.com/Joinjun001/CNU-Hackathon)
