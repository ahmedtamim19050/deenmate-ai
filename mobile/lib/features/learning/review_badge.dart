import 'package:flutter/material.dart';

import '../../core/widgets/common.dart';

/// Tells the reader plainly whether a scholar has reviewed this content yet.
class ReviewBadge extends StatelessWidget {
  const ReviewBadge({super.key, required this.status});

  final String status;

  @override
  Widget build(BuildContext context) => status == 'approved'
      ? StatusChip(label: context.l10n.scholarReviewed, icon: Icons.verified_outlined, tone: StatusTone.success)
      : StatusChip(label: context.l10n.awaitingReview, icon: Icons.hourglass_empty, tone: StatusTone.pending);
}
