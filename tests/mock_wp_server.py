#!/usr/bin/env python3
"""
Lightweight Mock WordPress Server running Word402 protocol logic.
Used for local testing and demonstration without needing a full WordPress/MySQL setup.
"""

import json
import time
import uuid
from http.server import HTTPServer, BaseHTTPRequestHandler

MOCK_POST_TITLE = "2026 AI Agentic Commerce Deep Dive Report"
MOCK_POST_MARKDOWN = """# 2026 AI Agentic Commerce Deep Dive Report

## Executive Summary
본 리포트는 2026년 급부상한 AI 에이전틱 커머스 시장의 구조와 x402 프로토콜을 분석합니다.

## 1. 패러다임의 전환
기존 웹 미디어의 광고/리퍼럴 수익 모델이 붕괴함에 따라, AI 에이전트가 직접 결제하는 x402 프로토콜이 새로운 글로벌 표준으로 채택되었습니다.

## 2. 블록체인 마이크로페이먼트의 필요성
신용카드 결제망은 고정 수수료와 본인인증 강제로 인해 머신 간 마이크로페이먼트를 처리할 수 없습니다. Base L2 및 Solana를 통한 소액 스테이블코인 전송만이 유일한 해결책입니다.

---
*Verified & Settled via Word402 Protocol*
"""

class MockWord402Handler(BaseHTTPRequestHandler):
    active_challenges = {}
    used_tx_hashes = set()

    def do_GET(self):
        if self.path != "/wp-json/word402/v1/posts/1":
            self.send_response(404)
            self.end_headers()
            self.wfile.write(b"Not Found")
            return

        x_payment = self.headers.get("X-Payment")

        # Step 1: No payment proof -> Issue HTTP 402
        if not x_payment:
            challenge_id = "chn_" + str(uuid.uuid4())[:8]
            expires_at = int(time.time()) + 300
            self.active_challenges[challenge_id] = expires_at

            www_auth = (
                f'x402 network="base-sepolia", currency="USDC", '
                f'amount="0.005", recipient="0x71C83638379188981650392b49B283946F174B29", '
                f'challenge="{challenge_id}"'
            )

            body = {
                "protocol": "x402",
                "version": "2.0",
                "status": "payment_required",
                "resource": {
                    "post_id": 1,
                    "title": MOCK_POST_TITLE,
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
                    "recipient": "0x71C83638379188981650392b49B283946F174B29",
                    "challenge_id": challenge_id,
                    "expires_at": expires_at,
                    "ttl_seconds": 300
                }
            }

            self.send_response(402)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("WWW-Authenticate", www_auth)
            self.send_header("X-402-Version", "2.0")
            self.send_header("X-402-Challenge-ID", challenge_id)
            self.end_headers()
            self.wfile.write(json.dumps(body, indent=2).encode("utf-8"))
            return

        # Step 2: Payment proof submitted -> Verify
        try:
            proof = json.loads(x_payment)
            tx_hash = proof.get("tx_hash")
            challenge_id = proof.get("challenge_id")
        except Exception:
            self.send_response(400)
            self.end_headers()
            self.wfile.write(b'{"error": "Invalid X-Payment payload"}')
            return

        # Check replay
        if tx_hash in self.used_tx_hashes:
            self.send_response(409)
            self.send_header("Content-Type", "application/json")
            self.end_headers()
            self.wfile.write(b'{"error": true, "code": "REPLAY_ATTACK_DETECTED", "message": "Transaction already redeemed"}')
            return

        # Record receipt
        self.used_tx_hashes.add(tx_hash)

        # Serve Markdown (200 OK)
        self.send_response(200)
        self.send_header("Content-Type", "text/markdown; charset=utf-8")
        self.send_header("X-402-Status", "settled")
        self.send_header("X-402-Tx-Hash", tx_hash)
        self.end_headers()
        self.wfile.write(MOCK_POST_MARKDOWN.encode("utf-8"))

def run_server(port=8089):
    server = HTTPServer(("127.0.0.1", port), MockWord402Handler)
    print(f"Mock WordPress Word402 Server running on http://127.0.0.1:{port}")
    server.serve_forever()

if __name__ == "__main__":
    run_server()
