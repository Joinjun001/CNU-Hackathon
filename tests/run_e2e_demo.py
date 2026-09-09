#!/usr/bin/env python3
"""
E2E Integration & Demo Runner
Spawns mock Word402 server, executes agent.py client, and validates the entire handshake.
"""

import subprocess
import time
import sys
import os

def main():
    print("================================================================")
    print("🚀 Word402 E2E 인티그레이션 및 핸드셰이크 시뮬레이션 데모 시작")
    print("================================================================")

    # 1. Start Mock WordPress Server
    server_process = subprocess.Popen(
        [sys.executable, "tests/mock_wp_server.py"],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )
    time.sleep(1.0) # Wait for server to bind port

    try:
        # 2. Run AI Agent Client against Mock Server
        print("\n[테스트 1] AI 에이전트의 정상 x402 결제 및 본문 취득 시연:")
        agent_cmd = [
            sys.executable,
            "agent-client/agent.py",
            "--url", "http://127.0.0.1:8089/wp-json/word402/v1/posts/1",
            "--simulate"
        ]
        result = subprocess.run(agent_cmd, capture_output=True, text=True)
        print(result.stdout)
        if result.stderr:
            print("STDERR:", result.stderr)

        if "온체인 영수증 검증 통과! 데이터 잠금 해제 성공!" in result.stdout:
            print(">>> ✅ 테스트 1 성공: 402 감지, 결제 생성, 마크다운 수신 완료!")
        else:
            print(">>> ❌ 테스트 1 실패")
            return False

        print("\n================================================================")
        print("[테스트 2] Replay Attack(동일 트랜잭션 영수증 재사용 공격) 차단 검증:")
        # We simulate reusing the same tx_hash against the mock server directly
        import urllib.request
        import json

        # Send request with a known reused tx_hash
        reused_hash = "0x" + "a" * 64
        payload = json.dumps({"tx_hash": reused_hash, "challenge_id": "test_chn"}).encode("utf-8")
        req1 = urllib.request.Request(
            "http://127.0.0.1:8089/wp-json/word402/v1/posts/1",
            headers={"X-Payment": payload.decode("utf-8")},
            method="GET"
        )
        res1 = urllib.request.urlopen(req1)
        print(f"  • 첫 번째 영수증 사용: HTTP {res1.getcode()} (정상 승인)")

        # Attempt replay
        try:
            req2 = urllib.request.Request(
                "http://127.0.0.1:8089/wp-json/word402/v1/posts/1",
                headers={"X-Payment": payload.decode("utf-8")},
                method="GET"
            )
            urllib.request.urlopen(req2)
            print(">>> ❌ Replay Attack 차단 실패: 중복 승인됨")
            return False
        except urllib.error.HTTPError as e:
            if e.code == 409:
                print(f"  • 두 번째 영수증 재사용 시도: HTTP {e.code} ({e.read().decode('utf-8').strip()})")
                print(">>> ✅ 테스트 2 성공: Replay Attack (HTTP 409) 방어 정상 동작 확인!")
            else:
                print(f">>> ❌ 예상치 못한 HTTP 코드: {e.code}")
                return False

    finally:
        server_process.terminate()
        server_process.wait()

    print("\n================================================================")
    print("🎉 모든 E2E 시뮬레이션 및 프로토콜 검증 테스트 통과 완료!")
    print("================================================================")
    return True

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
