# 🌐 [DEPLOY GUIDE] 다른 컴퓨터 배포 및 자동 동기화 가이드

이 문서는 다른 컴퓨터(호스트 머신)에서 Word402 워드프레스 서비스를 Docker로 구동하고, Cloudflare Tunnel을 통한 외부 공개 및 GitHub 자동 동기화를 운영하는 완벽 가이드입니다.

---

## 1. 준비 사항

* **운영체제**: Mac, Linux(Ubuntu 등), 또는 Windows(WSL2)
* **필수 설치 프로그램**:
  * [Git](https://git-scm.com/)
  * [Docker Desktop](https://www.docker.com/) (또는 Docker Engine + Docker Compose)

---

## 2. 3단계 빠른 배포 (Quick Start)

### Step 1. 저장소 클론
배포할 컴퓨터의 터미널에서 저장소를 클론합니다:
```bash
git clone https://github.com/Joinjun001/CNU-Hackathon.git
cd CNU-Hackathon
```

### Step 2. 환경 변수 설정
```bash
cp .env.example .env
```
* `.env` 파일을 열어 판매자 수취 지갑 주소(`SELLER_RECIPIENT_WALLET_ADDRESS`)를 입력합니다.

### Step 3. 원클릭 서버 가동
```bash
./scripts/start-server.sh
```
* **결과**:
  * 워드프레스 및 MySQL 컨테이너가 백그라운드로 실행됩니다.
  * Cloudflare Tunnel이 자동으로 생성되어 터미널에 **무료 공인 HTTPS 주소**(`https://xxxx.trycloudflare.com`)가 출력됩니다.
  * 포트포워딩, DDNS, SSL 인증서 발급이 필요 없습니다.

---

## 3. GitHub 변경 사항 실시간 자동 반영 (Auto-Deploy)

개발 컴퓨터에서 코드를 수정하고 GitHub `main` 브랜치에 `git push`하면, 배포 컴퓨터에서 자동으로 코드를 갱신하도록 설정합니다.

### 자동 동기화 워처(Watcher) 실행
배포 컴퓨터의 새 터미널 창에서 아래 명령어를 실행합니다:

```bash
# 백그라운드 무중단 실행
nohup ./scripts/auto-deploy.sh > auto-deploy.log 2>&1 &
```

* **동작 원리**:
  * 30초마다 GitHub 원격 저장소(`origin/main`)의 커밋을 체크합니다.
  * 새 커밋이 푸시되면 즉시 `git pull`을 실행합니다.
  * 워드프레스 컨테이너는 `./word402` 플러그인 소스코드를 **실시간 마운트(Bind Mount)**하고 있으므로, **컨테이너를 재시작하지 않아도 PHP 코드가 1초 만에 즉시 갱신**됩니다.
* **로그 확인**:
  ```bash
  tail -f auto-deploy.log
  ```

---

## 4. 외부 AI 에이전트 연동 테스트

어디서든 인터넷이 연결된 컴퓨터나 스마트폰에서 터널 주소를 통해 테스트할 수 있습니다:

```bash
# 에이전트 클라이언트 실행 시 타깃 URL에 Cloudflare 터널 주소 지정
python3 agent-client/agent.py --url https://your-tunnel-subdomain.trycloudflare.com/wp-json/word402/v1/posts/1
```

---

## 5. 유용한 유지보수 명령어

* **컨테이너 상태 확인**:
  ```bash
  docker compose ps
  ```
* **Cloudflare 터널 접속 주소 다시 확인하기**:
  ```bash
  docker compose logs cloudflared | grep -o 'https://.*trycloudflare.com'
  ```
* **워드프레스 로그 실시간 확인**:
  ```bash
  docker compose logs -f wordpress
  ```
* **서버 중지**:
  ```bash
  docker compose down
  ```
