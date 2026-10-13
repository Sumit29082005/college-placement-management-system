#!/usr/bin/env python3
"""
Placement analytics & reporting tools (Python companion to the PHP web app).

Usage:
    python placement_tools.py stats
    python placement_tools.py placed  --out placed_students.csv
    python placement_tools.py eligible --drive 1 --out eligible_drive1.csv
    python placement_tools.py chart   --out placement_chart.png

DB settings come from environment variables (defaults match config/db.php):
    DB_HOST, DB_NAME, DB_USER, DB_PASS
"""
import argparse
import csv
import os
import sys

import mysql.connector


def connect():
    return mysql.connector.connect(
        host=os.getenv("DB_HOST", "localhost"),
        database=os.getenv("DB_NAME", "placement_db"),
        user=os.getenv("DB_USER", "root"),
        password=os.getenv("DB_PASS", ""),
    )


def query(sql, args=()):
    conn = connect()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute(sql, args)
        return cur.fetchall()
    finally:
        conn.close()


def write_csv(path, rows):
    if not rows:
        print("No data to export.")
        return
    with open(path, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=rows[0].keys())
        w.writeheader()
        for r in rows:
            # prevent spreadsheet formula injection
            w.writerow({k: ("'" + v if isinstance(v, str) and v[:1] in "=+-@" else v) for k, v in r.items()})
    print(f"Saved {len(rows)} rows -> {path}")


def dept_stats():
    return query(
        """SELECT s.department,
                  COUNT(DISTINCT s.id) AS total,
                  COUNT(DISTINCT CASE WHEN a.status='selected' THEN s.id END) AS placed
           FROM students s LEFT JOIN applications a ON a.student_id = s.id
           GROUP BY s.department ORDER BY s.department"""
    )


def cmd_stats(_):
    rows = dept_stats()
    print(f"{'Department':<28}{'Students':>9}{'Placed':>8}{'Rate':>8}")
    print("-" * 53)
    tot = placed = 0
    for r in rows:
        pct = (r["placed"] * 100 / r["total"]) if r["total"] else 0
        print(f"{r['department']:<28}{r['total']:>9}{r['placed']:>8}{pct:>7.1f}%")
        tot += r["total"]
        placed += r["placed"]
    print("-" * 53)
    print(f"{'TOTAL':<28}{tot:>9}{placed:>8}{(placed * 100 / tot if tot else 0):>7.1f}%")
    pk = query(
        """SELECT MAX(d.package_lpa) AS highest, AVG(d.package_lpa) AS average
           FROM applications a JOIN drives d ON d.id = a.drive_id WHERE a.status='selected'"""
    )[0]
    print(f"\nHighest package: {pk['highest'] or 0} LPA | Average: {round(float(pk['average'] or 0), 2)} LPA")


def cmd_placed(args):
    rows = query(
        """SELECT u.name, s.roll_no, s.department, s.cgpa, c.name AS company, d.job_title, d.package_lpa
           FROM applications a
           JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id
           JOIN drives d ON d.id = a.drive_id JOIN companies c ON c.id = d.company_id
           WHERE a.status = 'selected' ORDER BY d.package_lpa DESC"""
    )
    write_csv(args.out, rows)


def cmd_eligible(args):
    drive = query("SELECT * FROM drives WHERE id = %s", (args.drive,))
    if not drive:
        sys.exit(f"Drive {args.drive} not found")
    d = drive[0]
    allowed = [x.strip() for x in d["allowed_departments"].split(",")]
    students = query(
        """SELECT u.name, u.email, s.roll_no, s.department, s.cgpa, s.backlogs
           FROM students s JOIN users u ON u.id = s.user_id"""
    )
    eligible = [
        s for s in students
        if s["cgpa"] >= d["min_cgpa"]
        and s["backlogs"] <= d["max_backlogs"]
        and ("ALL" in allowed or s["department"] in allowed)
    ]
    print(f"{len(eligible)} of {len(students)} students are eligible for '{d['job_title']}'")
    for s in eligible:
        print(f"  {s['roll_no']:<8} {s['name']:<22} CGPA {s['cgpa']}  {s['department']}")
    if args.out:
        write_csv(args.out, eligible)


def cmd_chart(args):
    try:
        import matplotlib
        matplotlib.use("Agg")
        import matplotlib.pyplot as plt
    except ImportError:
        sys.exit("Install matplotlib first: pip install matplotlib")
    rows = dept_stats()
    if not rows:
        sys.exit("No data available.")
    names = [r["department"] for r in rows]
    fig, ax = plt.subplots(figsize=(8, 4.5))
    x = range(len(rows))
    ax.bar([i - 0.2 for i in x], [r["total"] for r in rows], 0.4, label="Students", color="#c7d2fe")
    ax.bar([i + 0.2 for i in x], [r["placed"] for r in rows], 0.4, label="Placed", color="#4f46e5")
    ax.set_xticks(list(x))
    ax.set_xticklabels(names, rotation=20, ha="right")
    ax.set_title("Department-wise Placement")
    ax.legend()
    fig.tight_layout()
    fig.savefig(args.out, dpi=150)
    print(f"Chart saved -> {args.out}")


def main():
    p = argparse.ArgumentParser(description="Placement reporting tools")
    sub = p.add_subparsers(dest="cmd", required=True)
    sub.add_parser("stats").set_defaults(fn=cmd_stats)
    s = sub.add_parser("placed"); s.add_argument("--out", default="placed_students.csv"); s.set_defaults(fn=cmd_placed)
    s = sub.add_parser("eligible"); s.add_argument("--drive", type=int, required=True); s.add_argument("--out"); s.set_defaults(fn=cmd_eligible)
    s = sub.add_parser("chart"); s.add_argument("--out", default="placement_chart.png"); s.set_defaults(fn=cmd_chart)
    args = p.parse_args()
    try:
        args.fn(args)
    except mysql.connector.Error as e:
        sys.exit(f"Database error: {e}")


if __name__ == "__main__":
    main()
