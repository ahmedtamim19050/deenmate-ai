import 'dart:math' as math;

import 'package:flutter/material.dart';

/// A faint eight-point star lattice. It is decoration only, so it is excluded from semantics.
class GeometricPattern extends StatelessWidget {
  const GeometricPattern({super.key, this.color = Colors.white, this.opacity = 0.06, this.cell = 56});

  final Color color;
  final double opacity;
  final double cell;

  @override
  Widget build(BuildContext context) => ExcludeSemantics(
    child: CustomPaint(painter: _StarPainter(color.withValues(alpha: opacity), cell), size: Size.infinite),
  );
}

class _StarPainter extends CustomPainter {
  _StarPainter(this.color, this.cell);

  final Color color;
  final double cell;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1;
    for (var x = 0.0; x < size.width + cell; x += cell) {
      for (var y = 0.0; y < size.height + cell; y += cell) {
        _star(canvas, Offset(x, y), cell * 0.36, paint);
      }
    }
  }

  void _star(Canvas canvas, Offset c, double r, Paint paint) {
    for (final turn in [0.0, math.pi / 4]) {
      final path = Path();
      for (var i = 0; i < 4; i++) {
        final a = turn + i * math.pi / 2;
        final p = c + Offset(math.cos(a), math.sin(a)) * r;
        i == 0 ? path.moveTo(p.dx, p.dy) : path.lineTo(p.dx, p.dy);
      }
      path.close();
      canvas.drawPath(path, paint);
    }
  }

  @override
  bool shouldRepaint(_StarPainter old) => old.color != color || old.cell != cell;
}
