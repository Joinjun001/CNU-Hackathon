#!/usr/bin/env bash
# ==============================================================================
# Word402: Git 자동 배포 및 동기화 스크립트 (Auto-Deploy Watcher)
# ==============================================================================
# 이 스크립트를 배포 서버(컴퓨터)에서 실행해 두면, 원격 GitHub 저장소(main 브랜치)에
# 새로운 커밋이 푸시될 때마다 자동으로 코드를 감지하고 'git pull'을 수행합니다.
#
# 워드프레스 컨테이너는 './word402' 플러그인 디렉터리를 실시간 볼륨 마운트하고 있으므로,
# git pull 즉시 컨테이너 재시작 없이 새로운 코드가 워드프레스에 실시간 반영됩니다.
#
# 실행 방법:
#   $ chmod +x scripts/auto-deploy.sh
#   $ ./scripts/auto-deploy.sh             # 포그라운드 실행
#   $ nohup ./scripts/auto-deploy.sh &    # 백그라운드 무중단 실행
# ==============================================================================

INTERVAL=${1:-30} # 체크 주기 (기본값: 30초)
BRANCH="main"

echo "================================================================="
echo "🤖 Word402 GitHub 자동 동기화 워처(Watcher) 시작"
echo "  • 감시 대상 브랜치 : origin/${BRANCH}"
echo "  • 체크 주기        : ${INTERVAL}초"
echo "  • 동기화 모드      : 실시간 볼륨 마운트 즉시 반영"
echo "================================================================="

# Git 디렉터리 확인
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "❌ [ERROR] Git 저장소 내부가 아닙니다."
    exit 1
fi

while true; do
    # 원격 브랜치 변경 사항 확인
    git fetch origin "${BRANCH}" --quiet 2>/dev/null

    LOCAL_HASH=$(git rev-parse HEAD 2>/dev/null)
    REMOTE_HASH=$(git rev-parse "origin/${BRANCH}" 2>/dev/null)

    if [ "$LOCAL_HASH" != "$REMOTE_HASH" ]; then
        TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
        echo ""
        echo "[$TIMESTAMP] 🚀 새로운 커밋 감지! (${LOCAL_HASH:0:7} -> ${REMOTE_HASH:0:7})"
        echo "[$TIMESTAMP] 📥 원격 코드 동기화(git pull) 시작..."
        
        # Git pull 실행
        if git pull origin "${BRANCH}"; then
            echo "[$TIMESTAMP] ✅ 코드 동기화 완료! 워드프레스 플러그인에 즉시 반영되었습니다."
        else
            echo "[$TIMESTAMP] ⚠️ [경고] git pull 도중 충돌 또는 에러가 발생했습니다."
        fi
    fi

    sleep "${INTERVAL}"
done
