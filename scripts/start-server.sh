#!/usr/bin/env bash
# ==============================================================================
# Word402: 원클릭 서버 가동 스크립트 (One-Click Server Starter)
# ==============================================================================
# 이 스크립트는 다른 컴퓨터에서 워드프레스, MySQL, Cloudflare Tunnel을
# 한 번에 실행하고 외부 접속용 공인 HTTPS 주소를 화면에 출력해 줍니다.
#
# 실행 방법:
#   $ chmod +x scripts/start-server.sh
#   $ ./scripts/start-server.sh
# ==============================================================================

set -e

echo "================================================================="
echo "🐳 Word402 워드프레스 & Cloudflare Tunnel 서버 시작"
echo "================================================================="

# 1. Docker 실행 여부 확인
if ! docker info >/dev/null 2>&1; then
    echo "❌ [ERROR] Docker 데몬이 실행 중이지 않습니다. Docker Desktop을 먼저 켜주세요."
    exit 1
fi

# 2. .env 파일 확인
if [ ! -f .env ]; then
    echo "⚠️ .env 파일이 없습니다. .env.example을 복사하여 .env를 생성합니다."
    cp .env.example .env
    echo "💡 .env 파일에 판매자/에이전트 지갑 설정을 확인해 주세요."
fi

# 3. Docker Compose 실행
echo "🚀 Docker 컨테이너 구동 중 (WordPress, MySQL, Cloudflare Tunnel)..."
docker compose up -d

echo ""
echo "⏳ Cloudflare 보안 터널 연결 대기 중 (약 5~10초 소요)..."
sleep 6

# 4. Cloudflare 터널 공인 HTTPS 도메인 추출
TUNNEL_URL=""
for i in {1..10}; do
    TUNNEL_URL=$(docker compose logs cloudflared 2>&1 | grep -o 'https://[-a-zA-Z0-9@:%._\+~#=]*\.trycloudflare\.com' | tail -n 1 || true)
    if [ -n "$TUNNEL_URL" ]; then
        break
    fi
    echo "  • 터널 주소 수신 대기 중... ($i/10)"
    sleep 2
done

echo ""
echo "================================================================="
echo "🎉 Word402 서버 구동 완료!"
echo "================================================================="
echo "  • 로컬 접속 주소     : http://localhost:8080"
if [ -n "$TUNNEL_URL" ]; then
    echo "  • 외부 공인 HTTPS 주소: $TUNNEL_URL"
    echo "  • AI 에이전트 타깃 URL: $TUNNEL_URL/wp-json/word402/v1/posts/1"
else
    echo "  • 외부 공인 HTTPS 주소: 터널 주소를 확인하려면 아래 명령어를 입력하세요:"
    echo "    $ docker compose logs cloudflared | grep trycloudflare.com"
fi
echo "================================================================="
echo ""
echo "💡 팁: GitHub 푸시 시 자동 코드 갱신을 원하시면 새 터미널 창에서 아래를 실행하세요:"
echo "   $ ./scripts/auto-deploy.sh"
echo ""
